<?php declare(strict_types = 1);

namespace Deployer;

use function array_filter;
use function count;
use function ftp_connect;
use function ftp_delete;
use function ftp_mkdir;
use function ftp_put;
use function ftp_rename;
use function ftp_rmdir;
use function implode;
use function preg_split;
use function sprintf;

class FtpRemoteHandler implements RemoteHandler
{

	private $connection;

	/**
	 * @throws RemoteHandlerException
	 */
	public function __construct(
		string $host,
		?string $user = null,
		?string $password = null
	)
	{
		$connection = ftp_connect($host);
		if ($connection === false) {
			throw new FtpRemoteHandlerException(sprintf('Connecting to host "%s" failed.', $host));
		}
		if ($user !== null && $password !== null) {
			$result = ftp_login($connection, $user, $password);
			if ($result === false) {
				throw new FtpRemoteHandlerException('Login failed.');
			}
		}
		ftp_pasv($connection, true);

		$this->connection = $connection;
	}

	/**
	 * @return RemoteHandlerItem[]
	 * @throws RemoteHandlerException
	 */
	public function list(string $dir): array
	{
		$dir = $this->sanitizePath($dir);
		$items = @ftp_mlsd($this->connection, $dir);
		if ($items === false) {
			throw new FtpRemoteHandlerException(sprintf('Reading dir "%s" failed.', $dir));
		}
		if (count($items) <= 1) {
			throw new FtpRemoteHandlerException(sprintf('Path "%s" is not dir.', $dir));
		}
		$result = [];
		foreach ($items as $item) {
			if ($item['name'] === '.' || $item['name'] === '..') {
				continue;
			}

			$path = sprintf('%s/%s', $dir !== '/' ? $dir : '', $item['name']);
			$result[] = new RemoteHandlerItem(
				$item['name'],
				$path,
				$item['type'] === 'dir',
			);
		}

		return $result;
	}

	/**
	 * @throws RemoteHandlerException
	 */
	public function exists(string $path): bool
	{
		$dirNames = $this->splitPath($path);
		if (count($dirNames) === 0) {
			// root dir
			return true;
		}

		$itemName = array_pop($dirNames);
		$items = $this->list(implode('/', $dirNames));
		foreach ($items as $item) {
			if ($item->name === $itemName) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws RemoteHandlerException
	 */
	public function makeDir(string $dir): void
	{
		$dir = $this->sanitizePath($dir);
		$result = @ftp_mkdir($this->connection, $dir);
		if ($result === false) {
			throw new FtpRemoteHandlerException(sprintf('Creating dir "%s" failed.', $dir));
		}
	}

	/**
	 * @throws RemoteHandlerException
	 */
	public function move(string $oldPath, string $newPath): void
	{
		$oldPath = $this->sanitizePath($oldPath);
		$newPath = $this->sanitizePath($newPath);
		$result = @ftp_rename($this->connection, $oldPath, $newPath);
		if ($result === false) {
			throw new FtpRemoteHandlerException(sprintf('Renaming dir "%s" to "%s" failed.', $oldPath, $newPath));
		}
	}

	/**
	 * @throws RemoteHandlerException
	 */
	public function uploadFile(string $localFile, string $remoteFile): void
	{
		$remoteFile = $this->sanitizePath($remoteFile);
		$result = @ftp_put($this->connection, $remoteFile, $localFile);
		if ($result === false) {
			throw new FtpRemoteHandlerException(sprintf('Uploading file "%s" to "%s" failed.', $localFile, $remoteFile));
		}
	}

	/**
	 * @throws RemoteHandlerException
	 */
	public function remove(string $path): void
	{
		$path = $this->sanitizePath($path);
		$result = @ftp_delete($this->connection, $path);
		if ($result === false) {
			$exception = new FtpRemoteHandlerException('');
			$result = @ftp_rmdir($this->connection, $path);
			if ($result === false) {
				throw new FtpRemoteHandlerException(sprintf('Removing dir "%s" failed. %s', $path, $exception->getMessage()));
			}
		}
	}

	private function sanitizePath(string $path): string
	{
		$itemNames = $this->splitPath($path);

		return '/' . implode('/', $itemNames);
	}

	private function splitPath(string $path): array
	{
		return array_filter(
			preg_split('#[/\\\\]#', $path),
			static fn (string $name): bool => $name !== '',
		);
	}

	public function __destruct()
	{
		ftp_close($this->connection);
	}

}
