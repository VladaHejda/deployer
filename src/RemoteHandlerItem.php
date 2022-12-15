<?php declare(strict_types = 1);

namespace Deployer;

class RemoteHandlerItem
{

	public function __construct(
		public readonly string $name,
		public readonly string $path,
		public readonly bool $isDir,
	)
	{
	}

}
