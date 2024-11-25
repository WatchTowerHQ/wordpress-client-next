<?php
namespace WhatArmy\Watchtower\Iterators;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use UnexpectedValueException;

class ErrorHandlingRecursiveDirectoryIterator extends RecursiveDirectoryIterator
{
    public function __construct($path, $flags = 0)
    {
        parent::__construct($path, $flags);
    }

    public function getChildren(): RecursiveDirectoryIterator
    {
        try {
            return parent::getChildren();
        } catch (UnexpectedValueException $e) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log('WHTHQ: During Creating Backup We Handled Problem Reading (' . $this->getPathname() . ') And Safely Excluded It.');
            }
            // In Case We Can't Read Directory - Return WHT Backup DIR - It Must Be Present & It Will Be Skipped Later Because Of That Can Be Returned Multiple Times Without Affecting Returned Filesystem
            return new self(WHTHQ_BACKUP_DIR, FilesystemIterator::SKIP_DOTS);
        }
    }
}