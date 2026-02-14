<?php

declare(strict_types=1);

use Tivins\LSP\Config;

return (new Config())
    ->addDirectory('path/to/folder')
    ->excludeDirectory('path/to/folder/excluded')
    ->addFile('path/to/file')
    ->excludeFile('path/to/excluded/file');