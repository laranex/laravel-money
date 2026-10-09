<?php

declare(strict_types=1);

it('ships the same agent skill to Laravel Boost and to npx skills', function (): void {
    $root = dirname(__DIR__, 2);

    expect($root.'/skills/laravel-money/SKILL.md')->toBeFile()
        ->and(file_get_contents($root.'/skills/laravel-money/SKILL.md'))
        ->toBe(file_get_contents($root.'/resources/boost/skills/laravel-money/SKILL.md'));
});

it('shares its structure with goravel-money\'s skill', function (): void {
    $skill = (string) file_get_contents(dirname(__DIR__, 2).'/skills/laravel-money/SKILL.md');
    $headings = [];
    $inCode = false;

    foreach (explode("\n", str_replace("\r\n", "\n", $skill)) as $line) {
        if (str_starts_with($line, '```')) {
            $inCode = ! $inCode;
        }

        if (! $inCode && str_starts_with($line, '#')) {
            $headings[] = $line;
        }
    }

    expect($skill)->toStartWith("---\nname: laravel-money\n")
        ->and($headings)->toBe([
            '# Laravel Money',
            '## When to use',
            '## Install',
            '## Configure',
            '## Use',
            '### Build money',
            '### Calculate',
            '### Format and serialize',
            '### Store in the database',
            '### Handle errors',
            '## Test your app',
            '## Avoid',
        ]);
});
