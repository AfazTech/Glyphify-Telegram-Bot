<?php
namespace Bot\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Text
{
    public function __construct(
        public string $name, // کلید متن در فایل زبان یا دستور (مثلاً /start یا profile)
        public bool $isCommand = false // اگر true باشد، به عنوان دستور (شروع با /) شناسایی می‌شود
    ) {}
}
