<?php

interface FixtureInterfaceNoThrows
{
    public function run(): void;
}

class FixtureClassViolation implements FixtureInterfaceNoThrows
{
    public function run(): void
    {
        throw new \RuntimeException('fixture');
    }
}
