<?php declare(strict_types = 1);

namespace Deployer;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use function array_merge;
use function array_pop;
use function implode;
use function sprintf;

class Deployment
{

	private RemoteHandler $remoteHandler;
	private string $remoteProductionDirName;
	private Output $output;

	private string $localRootDir;
	private string $remoteRootParentDir;

	private string $revisionName = 'new';
	private string $oldRevisionName = 'old';

	/** @var Ignorer[] */
	private array $ignorers = [];

	private ?string $revisionDir = null;

	public function __construct(
		RemoteHandler $remoteHandler,
		string $localRootDir,
		string $remoteRootParentDir,
		string $remoteProductionDirName,
		Output $output = null
	)
	{
		$this->remoteHandler = $remoteHandler;
		$this->remoteProductionDirName = $remoteProductionDirName;
		$this->output = $output ?? new NullOutput();

		$localRootDir = rtrim($localRootDir, '/\\');
		$remoteRootParentDir = rtrim($remoteRootParentDir, '/\\');
		$this->localRootDir = $localRootDir !== '' ? $localRootDir : '/';
		$this->remoteRootParentDir = $remoteRootParentDir !== '' ? $remoteRootParentDir : '/';
	}

	public function setRevisionName(string $name): void
	{
		if ($name === '') {
			throw new DeploymentException('Revision name cannot be empty.');
		}
		if ($name === $this->oldRevisionName) {
			throw new DeploymentException('Old and new revision names cannot be same .');
		}

		$this->revisionName = $name;
	}

	public function setOldRevisionName(string $name): void
	{
		if ($name === '') {
			throw new DeploymentException('Revision name cannot be empty.');
		}
		if ($name === $this->revisionName) {
			throw new DeploymentException('Old and new revision names cannot be same .');
		}

		$this->oldRevisionName = $name;
	}

	public function addIgnorer(Ignorer $ignorer): void
	{
		$this->ignorers[] = $ignorer;
	}

	/**
	 * @param Ignorer[] $ignorers
	 */
	public function deployDirName(string $dirName, array $ignorers = []): void
	{
		$this->output->message(sprintf('Deploying "%s"', $dirName));
		$this->uploadDirNameRecursively($dirName, $ignorers);
	}

	public function createEmptyDirName(string $dirName): void
	{
		$dir = sprintf('%s/%s', $this->createRevisionDir(), $dirName);
		$this->makeDirRecursivelyIfNotExists($dir);
	}

	public function switchProductionWithNewRevision(): void
	{
		$revisionDir = $this->getRevisionDir();
		if (!$this->remoteHandler->exists($revisionDir)) {
			throw new DeploymentException(sprintf('Revision dir "%s" does not exist.', $revisionDir));
		}

		$oldProductionDirName = sprintf('%s-%s', $this->remoteProductionDirName, $this->oldRevisionName);
		$oldProductionDir = sprintf('%s/%s', $this->remoteRootParentDir, $oldProductionDirName);
		$this->removeDirIfExists($oldProductionDir);

		$productionDir = sprintf('%s/%s', $this->remoteRootParentDir, $this->remoteProductionDirName);
		$this->renameDirIfExists($productionDir, $oldProductionDirName);
		$this->renameDirIfExists($revisionDir, $this->remoteProductionDirName);
	}

	public function revert(): void
	{
		$oldProductionDirName = sprintf('%s-%s', $this->remoteProductionDirName, $this->oldRevisionName);
		$oldProductionDir = sprintf('%s/%s', $this->remoteRootParentDir, $oldProductionDirName);
		if (!$this->remoteHandler->exists($oldProductionDir)) {
			throw new DeploymentException(sprintf('Previous revision dir "%s" does not exist.', $oldProductionDir));
		}

		$productionDir = sprintf('%s/%s', $this->remoteRootParentDir, $this->remoteProductionDirName);
		$revisionDirName = sprintf('%s-%s', $this->remoteProductionDirName, $this->revisionName);
		$this->renameDirIfExists($productionDir, $revisionDirName);
		$this->renameDirIfExists($oldProductionDir, $this->remoteProductionDirName);
	}

	/**
	 * @param Ignorer[] $ignorers
	 */
	private function uploadDirNameRecursively(string $dirName, array $ignorers): void
	{
		$localDir = sprintf('%s/%s', $this->localRootDir, $dirName);
		if ($this->isIgnored($dirName, $ignorers)) {
			$this->output->message(sprintf('Ignoring dir "%s"', $localDir));
			return;
		}

		$remoteDir = sprintf('%s/%s', $this->createRevisionDir(), $dirName);
		$this->makeDirRecursivelyIfNotExists($remoteDir);

		$items = new RecursiveDirectoryIterator($localDir, FilesystemIterator::SKIP_DOTS);
		foreach ($items as $item) {
			if ($item->isDir()) {
				$this->uploadDirNameRecursively(sprintf('%s/%s', $dirName, $item->getFilename()), $ignorers);
			} else {
				$localFile = sprintf('%s/%s', $localDir, $item->getFilename());
				$localFileName = sprintf('%s/%s', $dirName, $item->getFilename());
				if ($this->isIgnored($localFileName, $ignorers)) {
					$this->output->message(sprintf('Ignoring file "%s"', $localFile));
				} else {
					$this->uploadFileIfNotExists($localFile, $remoteDir);
				}
			}
		}
	}

	/**
	 * @param Ignorer[] $additionalIgnorers
	 */
	private function isIgnored(string $path, array $additionalIgnorers): bool
	{
		$ignorers = array_merge($this->ignorers, $additionalIgnorers);

		foreach ($ignorers as $ignorer) {
			if ($ignorer->isIgnored($path)) {
				return true;
			}
		}

		return false;
	}

	private function createRevisionDir(): string
	{
		if ($this->revisionDir === null) {
			$revisionDir = $this->getRevisionDir();
			$this->makeDirRecursivelyIfNotExists($revisionDir);
			$this->revisionDir = $revisionDir;
		}

		return $this->revisionDir;
	}

	private function getRevisionDir(): string
	{
		return $this->revisionDir ?? sprintf(
			'%s/%s-%s',
			$this->remoteRootParentDir,
			$this->remoteProductionDirName,
			$this->revisionName,
		);
	}

	private function makeDirRecursivelyIfNotExists(string $dir): void
	{
		$dirNames = $this->splitPath($dir);
		$parentDir = '/';
		foreach ($dirNames as $dirName) {
			$currentDir = $parentDir . $dirName;
			if (!$this->remoteHandler->exists($currentDir)) {
				$this->output->message(sprintf('Creating dir "%s"', $currentDir));
				$this->remoteHandler->makeDir($currentDir);
			}
			$parentDir = sprintf('%s/', $currentDir);
		}
	}

	private function uploadFileIfNotExists(string $localFile, string $remoteDir): void
	{
		$localDirNames = $this->splitPath($localFile);
		$fileName = array_pop($localDirNames);
		$remoteFile = sprintf('/%s/%s', implode('/', $this->splitPath($remoteDir)), $fileName);

		if (!$this->remoteHandler->exists($remoteFile)) {
			$this->output->message(sprintf('Uploading "%s" to "%s"', $localFile, $remoteFile));
			$this->remoteHandler->uploadFile($localFile, $remoteFile);
		} else {
			$this->output->message(sprintf('File "%s" already exists', $remoteFile));
		}
	}

	private function renameDirIfExists(string $dir, string $newDirName): void
	{
		if (!$this->remoteHandler->exists($dir)) {
			return;
		}

		$dirNames = $this->splitPath($dir);
		array_pop($dirNames);
		$parentDir = implode('/', $dirNames);
		$newDir = sprintf('%s/%s', $parentDir, $newDirName);
		$this->output->message(sprintf('Renaming dir "%s" to "%s"', $dir, $newDir));
		$this->remoteHandler->move($dir, $newDir);
	}

	private function removeDirIfExists(string $dir): void
	{
		if (!$this->remoteHandler->exists($dir)) {
			return;
		}

		$removeDir = function (string $subDir) use (&$removeDir): void {
			foreach ($this->remoteHandler->list($subDir) as $item) {
				if ($item->isDir) {
					$removeDir($item->path);
				} else {
					$this->output->message(sprintf('Removing file "%s"', $item->path));
					$this->remoteHandler->remove($item->path);
				}
			}
			$this->output->message(sprintf('Removing dir "%s"', $subDir));
			$this->remoteHandler->remove($subDir);
		};

		$removeDir($dir);
	}

	private function splitPath(string $path): array
	{
		return array_filter(
			preg_split('#[/\\\\]#', $path),
			static fn (string $name): bool => $name !== '',
		);
	}

}
