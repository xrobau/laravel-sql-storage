<?php

namespace xrobau\LaravelSqlStorage;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use xrobau\LaravelSqlStorage\Models\FileModel;

class SqlStorageDriver
{
	/** Laravel Config array */
	protected $config;
	/** @var \Illuminate\Database\Query\Builder $conn */
	protected $conn;

	private ?string $uuid = null;

	/**
	 * @param \Illuminate\Contracts\Foundation\Application $app
	 * @param mixed $config
	 * @return void
	 */
	public function __construct(Application $app, $config)
	{
		$this->config = $config;
		$this->conn = DB::connection($this->config['connection'])->table($this->config['table']);
	}

	/**
	 * The 'folder' of this storage. It doesn't NEED to be a uuid, but
	 * you should use one just for consistency
	 *
	 * @param string $uuid
	 * @return static
	 */
	public function usingUuid(string $uuid): static
	{
		$this->uuid = $uuid;
		return $this;
	}

	/**
	 * Generate the SQL query params for the file/folder that is being
	 * requested
	 *
	 * @param string $fullpath
	 * @return array
	 * @throws \Exception
	 */
	public function getQueryParams(string $fullpath): array
	{
		if ($this->uuid === null) {
			throw new \Exception("No UUID provided, can not continue");
		}
		$chunks = explode("/", $fullpath);
		$filename = array_pop($chunks);
		if (mb_strlen($filename) > 120) {
			throw new \Exception("Filename $filename too long");
		}
		$path = join("/", $chunks);
		$params = ['uuid' => $this->uuid, 'path' => $path];
		if ($filename !== '') {
			// No file was provided, so we're globbing
			$params['filename'] = $filename;
		}
		return $params;
	}

	/**
	 * Return the query builder for the full path requested
	 *
	 * @param mixed $fullpath
	 * @return \Illuminate\Database\Query\Builder
	 */
	public function getRawBuilder($fullpath): Builder
	{
		return $this->conn->where($this->getQueryParams($fullpath));
	}

	/**
	 * Superset of 'getRawBuilder' - returns the Eloquent Builder
	 *
	 * @param mixed $fullpath
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function getFileModelBuilder($fullpath): EloquentBuilder
	{
		$q = $this->getQueryParams($fullpath);
		return FileModel::where($q);
	}

	/**
	 * Does this file exist?
	 *
	 * @param mixed $path
	 * @return boolean
	 * @throws \Exception
	 */
	public function exists($path)
	{
		return $this->getFileModelBuilder($path)->exists();
	}

	/**
	 * Return the first entry for path, or throw if it doesn't
	 *
	 * @param mixed $path
	 * @return \xrobau\LaravelSqlStorage\Models\FileModel
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function getModel($path): FileModel
	{
		$entry = $this->getFileModelBuilder($path)->first();

		if (!$entry) {
			throw new FileNotFoundException($path);
		}
		return $entry;
	}

	/**
	 * Get the contents of the path specified
	 *
	 * @param mixed $path
	 * @return mixed
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function get($path)
	{
		// getModel will throw if it doesn't exist
		$entry = $this->getModel($path);
		return $entry->contents;
	}

	/**
	 * Store the $contents at $path
	 *
	 * @param mixed $path
	 * @param mixed $contents
	 * @param array $options
	 * @return \xrobau\LaravelSqlStorage\Models\FileModel
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public function put($path, $contents, $options = [])
	{
		$q = $this->getQueryParams($path);
		return FileModel::updateOrInsert($q, ['contents' => $contents]);
	}

	/**
	 * Prepend $contents to the existing file $path
	 *
	 * @param mixed $path
	 * @param mixed $contents
	 * @return void
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 * @throws \Throwable
	 */
	public function prepend($path, $contents)
	{
		$existingContents = $this->get($path);
		$newContents = $contents . $existingContents;
		$this->put($path, $newContents);
	}

	/**
	 * Append $contents to the existing file $path
	 *
	 * @param mixed $path
	 * @param mixed $contents
	 * @return void
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 * @throws \Throwable
	 */
	public function append($path, $contents)
	{
		$existingContents = $this->get($path);
		$newContents = $existingContents . $contents;
		$this->put($path, $newContents);
	}

	/**
	 * Delete the file at $path
	 *
	 * @param mixed $path
	 * @return void
	 * @throws \Exception
	 */
	public function delete($path)
	{
		FileModel::purge($this->getQueryParams($path));
	}

	/**
	 * Copy the file $from/$to
	 *
	 * @param mixed $from
	 * @param mixed $to
	 * @return void
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 * @throws \Throwable
	 */
	public function copy($from, $to)
	{
		$contents = $this->get($from);
		$this->put($to, $contents);
	}

	/**
	 * Move the file $from/to
	 *
	 * @param mixed $from
	 * @param mixed $to
	 * @return void
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 * @throws \Throwable
	 */
	public function move($from, $to)
	{
		$contents = $this->get($from);
		$this->put($to, $contents);
		$this->delete($from);
	}

	/**
	 * Return the size, in bytes, of $path
	 *
	 * @param mixed $path
	 * @return mixed
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function size($path)
	{
		$entry = $this->getModel($path);
		return $entry->filesize;
	}

	/**
	 * Get the last modified time of $path
	 *
	 * @param mixed $path
	 * @return mixed
	 * @throws \Exception
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function lastModified($path)
	{
		$entry = $this->getModel($path);
		return $entry->updated_at->timestamp;
	}

	public function files($directory)
	{
		throw new \Exception("Unimplemented");
	}

	public function allFiles($directory)
	{
		throw new \Exception("Unimplemented");
	}

	public function directories($directory)
	{
		throw new \Exception("Unimplemented");
	}

	public function allDirectories($directory)
	{
		throw new \Exception("Unimplemented");
	}

	public function makeDirectory($path)
	{
		throw new \Exception("Unimplemented");
	}

	public function deleteDirectory($directory)
	{
		throw new \Exception("Unimplemented");
	}
}
