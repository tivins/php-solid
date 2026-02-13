<?php

interface FixtureInterfaceWithThrows
{
    /** @throws \RuntimeException */
    public function run(): void;
}

class FixtureClassOk implements FixtureInterfaceWithThrows
{
    public function run(): void
    {
        throw new \RuntimeException('allowed');
    }
}
