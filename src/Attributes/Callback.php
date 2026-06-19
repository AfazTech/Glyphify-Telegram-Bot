<?php
namespace Bot\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Callback
{
    public function __construct(
        public string $data,
        public bool $isStep = false
    ) {}
}
