<?php
namespace Bot\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Text
{
    public function __construct(
        public string $name, 
        public bool $isCommand = false,
        public int $priority = 0  // بالاترین عدد = اولویت بالاتر
    ) {}
}
