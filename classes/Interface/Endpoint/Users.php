<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\User;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Interface\EntryWith\UserWithRelationship;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\UserDomainValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;
use UOPF\Validator\Extension\SearchKeywordsValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Exception\ValidationCodeException;
use PragmaRX\Random\Random;

/**
 * Users
 */
final class Users extends Endpoint {
    public function read(Response $response): EmbeddableList {
        $filtered = $this->filterQuery(new DictionaryValidator([
            'page' => new DictionaryValidatorElement(
                label: 'Page',
                default: 1,
                validator: new PageNumberValidator()
            ),

            'perPage' => new DictionaryValidatorElement(
                label: 'Number per Page',
                default: 10,
                validator: new NumberPerPageValidator()
            ),

            'order' => new DictionaryValidatorElement(
                label: 'Order',
                default: OrderValidator::DESCENDING,
                validator: new OrderValidator()
            ),

            'orderby' => new DictionaryValidatorElement(
                label: 'Orderby',
                default: 'followers',

                validator: new EnumerationValidator([
                    'followers',
                    'posts',
                    'registered'
                ])
            ),

            'search' => new DictionaryValidatorElement(
                label: 'Search Keywords',
                validator: new SearchKeywordsValidator()
            ),

            'domain' => new DictionaryValidatorElement(
                label: 'Personal Domain',
                validator: new UserDomainValidator()
            ),

            'relationshipWith' => new DictionaryValidatorElement(
                label: 'Attaching Relationships with',
                validator: new IdValidator()
            ),
        ]));

        if ($filtered['orderby'] === 'registered') {
            if ($this->isAdministrative())
                $orderby = $filtered['orderby'];
            else
                $this->throwPermissionDeniedException();
        } else {
            $orderby = "_{$filtered['orderby']}";
        }

        $where = [
            'TOTAL' => true,
            'ORDER' => [$orderby => $filtered['order']],
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page'])
        ];

        if (isset($filtered['domain']))
            $where['AND'] = ['domain' => $filtered['domain']];

        if (isset($filtered['search'])) {
            $where['OR # search'] = Database::getSearchClause(
                $filtered['search'],

                [
                    'display_name',
                    'description'
                ]
            );
        }

        $retrieved = UserManager::queryEntries($where);
        $entries = $retrieved->entries;

        if (isset($filtered['relationshipWith'])) {
            if (!$this->isAdministrative())
                $this->throwPermissionDeniedException();

            foreach ($entries as $key => $user)
                $entries[$key] = new UserWithRelationship($user, $filtered['relationshipWith']);
        }

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($entries);
    }

    public function write(Response $response): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'case' => new DictionaryValidatorElement(
                label: 'Case',
                required: true,
                validator: new IdValidator()
            ),

            'code' => new DictionaryValidatorElement(
                label: 'Validation Code',
                required: true,
                validator: new ValidationCodeValidator()
            )
        ]));

        $userOrException = Database::transaction(function () use (&$filtered) {
            if (!$locked = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                throw new ParameterException('Case does not exist.', 'case');

            if ($locked['type'] !== 'registration')
                throw new ParameterException('Invalid case.', 'case');

            try {
                CaseManager::validateLockedEmailValidationCode($locked, $filtered['code']);
            } catch (ValidationCodeException $exception) {
                return new ParameterException($exception->getMessage(), previous: $exception);
            }

            CaseManager::closeLockedValidationCode($locked);

            return UserManager::register(
                username: $locked['metadata']['data']['username'],
                displayName: static::generateRandomDisplayName(),
                email: $locked['metadata']['data']['email'],
                passwordHash: $locked['metadata']['data']['passwordHash'],
            );
        });

        if ($userOrException instanceof \Exception)
            throw $userOrException;
        else
            $user = $userOrException;

        if (!isset($this->request->user))
            $this->request->setUser($user);

        static::setTokenOnResponse($response, $user, time() + (60 * 60 * 24));
        return $user;
    }

    protected static function generateRandomDisplayName(): string {
        $random = new Random();
        return $random->get();
    }
}
