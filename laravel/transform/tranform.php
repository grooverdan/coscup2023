<?php

require 'vendor/autoload.php';

use Michelf\MarkdownExtra;

$parser = new MarkdownExtra;
$parser->code_block_content_func = function ($code, $language) {
    return highlight_string($code, true);
};

$my_text = file_get_contents( 'php://stdin' );

print( $parser->transform($my_text) );
