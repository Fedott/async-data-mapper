<?php declare(strict_types=1);
namespace Fedot\DataMapper;

interface Identifiable
{
    public function getId(): string;
}
