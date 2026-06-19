<?php
namespace Bot\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Step
{
    public function __construct(
        public string $name,
        public ?string $nextStep = null,
        public bool $autoClear = true
    ) {}
}
