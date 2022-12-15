<?php declare(strict_types = 1);

namespace Deployer;

interface Ignorer
{

	public function isIgnored(string $item): bool;

}
