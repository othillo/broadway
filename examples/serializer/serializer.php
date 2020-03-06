<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) 2020 Broadway project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';

class SerializeMe implements Broadway\Serializer\Serializable
{
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public static function deserialize(array $data)
    {
        return new self($data['message']);
    }

    public function serialize()
    {
        return [
            'message' => $this->message,
        ];
    }
}

// Setup the simple serializer
$serializer = new Broadway\Serializer\SimpleInterfaceSerializer();

// Create something to serialize
$serializeMe = new SerializeMe("Hi, i'm serialized?");

// Serialize
$serialized = $serializer->serialize($serializeMe);
var_dump($serialized);

// Deserialize
$deserialized = $serializer->deserialize($serialized);
var_dump($deserialized);
