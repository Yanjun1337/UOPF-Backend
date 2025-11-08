<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Model\Record;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\RecordTitleValidator;
use UOPF\Validator\Extension\RecordContentValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Posts
 */
final class Posts extends Endpoint {
    public function write(Response $response): Record {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'title' => new DictionaryValidatorElement(
                label: 'Post Title',
                validator: new RecordTitleValidator()
            ),

            'content' => new DictionaryValidatorElement(
                label: 'Post Content',
                required: true,
                validator: new RecordContentValidator()
            ),

            'parent' => new DictionaryValidatorElement(
                label: 'Post Parent',
                validator: new IdValidator()
            )
        ]));

        try {
            $post = RecordManager::publish(
                type: 'post',
                user: $current['id'],
                title: $filtered['title'] ?? null,
                content: $filtered['content'],
                parent: $filtered['parent'] ?? null,
                userAgent: $this->request->headers->get('User-Agent')
            );
        } catch (RecordUpdateException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        $response->setStatusCode(201);
        return $post;
    }
}
