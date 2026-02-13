<?php

interface FixtureInterfaceWithThrowsOk
{
    /**
     * @throws RuntimeException
     */
    public function run(): void;
}

class FixtureClassCompliant implements FixtureInterfaceWithThrowsOk
{
    public function run(): void
    {
        throw new \RuntimeException('allowed');
    }
}
