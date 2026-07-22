<?php

declare(strict_types=1);
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Nowo\WordTemplateBundle\WordTemplateBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

return [
    FrameworkBundle::class         => ['all' => true],
    TwigBundle::class              => ['all' => true],
    DebugBundle::class             => ['dev' => true],
    WebProfilerBundle::class       => ['dev' => true],
    WordTemplateBundle::class      => ['all' => true],
    NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
];
