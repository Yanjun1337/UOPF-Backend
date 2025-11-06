<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\ImageUploadParameters;
use UOPF\Model\Image;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Exception\ImageUploadException;

/**
 * Post Image Uploading
 */
final class PostsUpload extends Endpoint {
    public function write(Response $response): Image {
        if (!isset($this->request->user))
            $this->throwUnauthorizedException();

        $parameters = new ImageUploadParameters(
            user: $this->request->user['id']
        );

        try {
            $id = ImageManager::uploadFromBody($this->request, $parameters);
        } catch (ImageUploadException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        if ($image = ImageManager::fetchEntry($id))
            return $image;
        else
            $this->throwInconsistentInternalDataException();
    }
}
