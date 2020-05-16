<?php
/**
 * Copyright © 2020 Roma Technology Limited. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace RTech\Parcel2Go\Webservice\Exception;

class Parcel2GoCommunicationException extends \Exception {
    const SETUP_EXCEPTION_MESSAGE   = 'Parcel2Go API connection could not be prepared, please check your configuration.';
    const RUNTIME_EXCEPTION_MESSAGE = 'Parcel2Go API connection could not be established.';

    /**
     * @param string $message
     *
     * @return static
     */
    public static function setup($message)
    {
        $message = sprintf('%s %s', self::SETUP_EXCEPTION_MESSAGE, $message);
        return new static($message);
    }

    /**
     * @param string $message
     *
     * @return static
     */
    public static function runtime($message)
    {
        $message = sprintf('%s %s', self::RUNTIME_EXCEPTION_MESSAGE, $message);
        return new static($message);
    }
}