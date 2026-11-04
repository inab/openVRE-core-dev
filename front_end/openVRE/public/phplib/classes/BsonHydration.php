<?php

namespace OpenVRE;

use MongoDB\BSON\Persistable;

/**
 * Helpers for Persistable classes to hydrate typed properties from BSON data.
 *
 * Depending on the typeMap, nested BSON arrays/documents are returned as stdClass,
 * BSONArray or BSONDocument objects, which cannot be assigned to `array` typed properties.
 */
trait BsonHydration
{
    /**
     * Assigns each known property from $data, converting values to arrays for `array` typed properties.
     * Keys listed in $skip are ignored.
     */
    private function hydrateFromBson(array $data, array $skip = []): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $skip, true) || !property_exists($this, $key)) {
                continue;
            }

            $type = (new \ReflectionProperty($this, $key))->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                $value = self::bsonToArray($value);
            }

            $this->$key = $value;
        }
    }

    /**
     * Recursively converts BSON container objects into PHP arrays, leaving Persistable objects untouched.
     */
    private static function bsonToArray(mixed $value): mixed
    {
        if ($value instanceof Persistable) {
            return $value;
        }
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        return array_map(
            static fn($item) => is_object($item) || is_array($item) ? self::bsonToArray($item) : $item,
            $value
        );
    }
}
