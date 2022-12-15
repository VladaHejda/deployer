<?php declare(strict_types = 1);

namespace Deployer;

interface RemoteHandler
{

	/**
	 * @return RemoteHandlerItem[]
	 * @throws RemoteHandlerException
	 */
	public function list(string $dir): array;

	/**
	 * @throws RemoteHandlerException
	 */
	public function exists(string $path): bool;

	/**
	 * @throws RemoteHandlerException
	 */
	public function makeDir(string $dir): void;

	/**
	 * @throws RemoteHandlerException
	 */
	public function move(string $oldPath, string $newPath): void;

	/**
	 * @throws RemoteHandlerException
	 */
	public function uploadFile(string $localFile, string $remoteFile): void;

	/**
	 * @throws RemoteHandlerException
	 */
	public function remove(string $path): void;

}
