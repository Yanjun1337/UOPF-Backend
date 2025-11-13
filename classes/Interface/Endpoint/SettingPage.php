<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Settings as SettingsManager;
use UOPF\Setting\Page;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Exception\DictionaryValidationException;

/**
 * Setting Page
 */
final class SettingPage extends Endpoint {
    public function read(Response $response): array {
        if (!$this->isAdministrative())
            $this->throwPermissionDeniedException();

        return $this->getSettingPage()->getSchema();
    }

    public function write(Response $response): array {
        if (!$this->isAdministrative())
            $this->throwPermissionDeniedException();

        $page = $this->getSettingPage();
        $data = $this->request->getPayload()->all();

        try {
            $page->set($data);
        } catch (DictionaryValidationException $exception) {
            throw new ParameterException(
                $exception->getLabeledMessage(),
                $exception->elementKey
            );
        }

        return $page->getSchema();
    }

    protected function getSettingPage(): Page {
        $name = $this->query['name'];
        $pages = SettingsManager::getProperty('pages');

        if (isset($pages[$name]))
            return $pages[$name];
        else
            $this->throwNotFoundException();
    }
}
