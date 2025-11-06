<?php
declare(strict_types=1);
namespace UOPF;

final class ImageUploadParameters {
    public function __construct(
        /**
         * The ID of the user who uploaded the image.
         */
        public readonly ?int $user = null
    ) {}
}
