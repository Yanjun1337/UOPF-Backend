<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\Utilities;
use UOPF\Exception;
use UOPF\ModelFieldType;
use UOPF\DatabaseLockType;
use UOPF\RetrievedEntries;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Topic as TopicManager;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Facade\Manager\Record as RecordManager;
use WhichBrowser\Parser as WhichBrowserParser;
use const PREG_SPLIT_DELIM_CAPTURE;

final class Record extends Model {
    public function fetchImages(): array {
        return Database::transaction(function () {
            if (!$locked = RecordManager::fetchEntryDirectly($this->data['id'], lock: DatabaseLockType::read))
                throw new Exception('Failed to fetch image.');

            return $this->fetchImagesDirectly();
        });
    }

    public function fetchImagesDirectly(): array {
        $conditions = [
            'status' => 'publish',
            'record' => $this->data['id']
        ];

        return ImageManager::queryEntries([
            'AND' => $conditions,
            'ORDER' => ['position' => 'ASC']
        ])->entries;
    }

    public function renderContent(string $content, bool $wrapParagraphsAround = false): string {
        if ($this->isLong()) {
            return Utilities::textMap([static::class, 'renderTextNodeInHTML'], $content);
        } else {
            $rendered = static::renderReferencesInText($content);

            if ($wrapParagraphsAround)
                return Utilities::wrapParagraphsAround($rendered);
            else
                return $rendered;
        }
    }

    public function renderHierarchicalContent(): string {
        $rendered = $this->renderContent($this->data['content']);

        if (($image = $this->renderFirstImageButton()) !== null)
            $rendered .= $image;

        if ($current = $this->getParent()) {
            while (
                (mb_strlen($rendered) < (300 * 2)) &&
                ($parent = $current->getParent())
            ) {
                if (!$user = UserManager::fetchEntry($current['user']))
                    break;

                $rendered .= $current->renderContent(sprintf(
                    ' // @%1$s: %2$s',
                    $user['username'],
                    $current['content']
                ));

                if ($image = $this->renderFirstImageButton())
                    $rendered .= $image;

                $current = $parent;
            }
        }

        return Utilities::wrapParagraphsAround($rendered);
    }

    public function getParent(): ?static {
        if (isset($this->data['parent']))
            return RecordManager::fetchEntry($this->data['parent']);
        else
            return null;
    }

    public function getRoot(): ?static {
        if (!$root = $this->getParent())
            return null;

        while ($parent = $root->getParent())
            $root = $parent;

        return $root;
    }

    public function getDepth(): int {
        if ($parent = $this->getParent())
            return 1 + $parent->getDepth();
        else
            return 1;
    }

    public function getChildrenRecursively(
        int $page,
        int $perPage,
        string $order,
        string $orderby,
        ?array $exclude = null
    ): RetrievedEntries {
        $limit = Database::getPagingLimit($perPage, $page);

        if (is_array($limit))
            $limitClause = sprintf('%1$s, %2$s', $limit[0], $limit[1]);
        else
            $limitClause = strval($limit);

        if (empty($exclude)) {
            $where = '';
        } else {
            $where = sprintf(
                'WHERE `id` NOT IN (%s)',
                implode(', ', $exclude)
            );
        }

        $sql = trim("
SELECT SQL_CALC_FOUND_ROWS * FROM (
    SELECT * FROM (
        SELECT * FROM `records`
        WHERE
            `type` = :type AND
            `status` = :status AND
            `affiliated_to` = :affiliated_to AND
            `parent` IS NOT NULL
        ORDER BY `parent`, `id`
    ) `sorted`, (
        SELECT @pv := :current
    ) `initialization`
    WHERE
        find_in_set(`parent`, @pv) AND
        length(@pv := concat(@pv, ',', `id`))
    ORDER BY `parent`, `id`
) `filtered`
{$where}
ORDER BY `{$orderby}` {$order}
LIMIT {$limitClause};
        ");

        return RecordManager::queryEntriesArbitrarily($sql, [
            ':type' => $this->data['type'],
            ':affiliated_to' => $this->data['affiliated_to'],
            ':current' => $this->data['id'],
            ':status' => 'publish'
        ]);
    }

    public function getPlatform(): ?string {
        if (($userAgent = $this->data['user_agent']) === null)
            return null;

        $parser = @new WhichBrowserParser($userAgent);

        switch (true) {
            case $parser->isOs( 'Windows' ):
                return 'windows';

            case $parser->isOs('OS X'):
                return 'macintosh';

            case $parser->isType('mobile'):
                return 'mobile';

            default:
                return null;
        }
    }

    protected function renderFirstImageButton(): ?string {
        if ($this->isLong())
            return null;

        $images = $this->fetchImages();

        if (isset($images[0])) {
            return sprintf(
                '<a data-image="%s">View Image</a>',
                Utilities::escape($images[0]->getSource())
            );
        } else {
            return null;
        }
    }

    public function renderField(string $field): string {
        switch ($field) {
            case 'title':
                return Utilities::escape($this->data[$field]);

            case 'content':
                return $this->renderContent($this->data[$field], true);

            default:
                $this->throwUnsupportedEditableFieldException();
        }
    }

    public function canBeEditedBy(User $user): bool {
        if ($user->isAdministrator())
            return true;

        if ($this->data['status'] == 'publish')
            return $this->data['user'] === $user['id'];

        return false;
    }

    public function isLong(): bool {
        return isset($this->data['title']);
    }

    public function extractTopics(): array {
        if ($this->isLong())
            return TopicManager::extractFromHTML($this->data['content']);
        else
            return TopicManager::extractFromText($this->data['content']);
    }

    public static function renderTextNodeInHTML(string $html): string {
        return static::renderReferencesInText(Utilities::unescape($html));
    }

    public static function renderReferencesInText(string $text): string {
        $separator = '/((?:#|@)(?:[a-zA-Z0-9_\-]{1,32}))/';
        $split = preg_split($separator, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        return implode('', array_map([static::class, 'renderSplitBlock'], $split));
    }

    protected static function renderSplitBlock(string $text): string {
        if (preg_match('/^(#|@)([a-zA-Z0-9_\-]{1,32})$/', $text, $matches) > 0)
            return static::renderReferenceInContent( $matches );
        else
            return Utilities::escape($text);
    }

    protected static function renderReferenceInContent(array $matches): string {
        $reference = array(
            'full' => $matches[0],
            'symbol' => $matches[1],
            'content' => $matches[2],
            'output' => Utilities::escape($matches[0])
        );

        if ($reference['symbol'] === '@') {
            if ($user = UserManager::fetchEntry($reference['content'], 'username')) {
                return sprintf(
                    '<a data-at="%1$s">@%2$s</a>',
                    intval($user['id']),
                    $user->renderField('username')
                );
            } else {
                return $reference['output'];
            }
        } else {
            return sprintf(
                '<a data-topic="%1$s">%2$s</a>',
                Utilities::escape($reference['content']),
                $reference['output']
            );
        }
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'user' => ModelFieldType::integer,
            'parent' => ModelFieldType::integer,
            'affiliated_to' => ModelFieldType::integer,
            'title' => ModelFieldType::string,
            'content' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'type' => ModelFieldType::string,
            'status' => ModelFieldType::string,
            'user_agent' => ModelFieldType::string,

            '_likes' => ModelFieldType::integer,
            '_dislikes' => ModelFieldType::integer,
            '_comments' => ModelFieldType::integer,
            '_reposts' => ModelFieldType::integer
        ];
    }
}
