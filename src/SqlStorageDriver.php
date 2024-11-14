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
    protected $config;
    /** @var \Illuminate\Database\Query\Builder $conn */
    protected $conn;

    private ?string $uuid = null;

    public function __construct(Application $app, $config)
    {
        $this->config = $config;
        $this->conn = DB::connection($this->config['connection'])->table($this->config['table']);
    }

    public function usingUuid(string $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

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

    public function getRawBuilder($fullpath): Builder
    {
        return $this->conn->where($this->getQueryParams($fullpath));
    }

    public function getFileModelBuilder($fullpath): EloquentBuilder
    {
        $q = $this->getQueryParams($fullpath);
        return FileModel::where($q);
    }

    public function exists($path)
    {
        return $this->getFileModelBuilder($path)->exists();
    }

    public function getModel($path): FileModel
    {
        $entry = $this->getFileModelBuilder($path)->first();

        if (!$entry) {
            throw new FileNotFoundException($path);
        }
        return $entry;
    }

    public function get($path)
    {
        // getModel will throw if it doesn't exist
        $entry = $this->getModel($path);
        return $entry->contents;
    }

    public function put($path, $contents, $options = [])
    {
        $q = $this->getQueryParams($path);
        return FileModel::updateOrInsert($q, ['contents' => $contents]);
    }

    public function prepend($path, $contents)
    {
        $existingContents = $this->get($path);
        $newContents = $contents . $existingContents;
        $this->put($path, $newContents);
    }

    public function append($path, $contents)
    {
        $existingContents = $this->get($path);
        $newContents = $existingContents . $contents;
        $this->put($path, $newContents);
    }

    public function delete($path)
    {
        FileModel::purge($this->getQueryParams($path));
    }

    public function copy($from, $to)
    {
        $contents = $this->get($from);
        $this->put($to, $contents);
    }

    public function move($from, $to)
    {
        $contents = $this->get($from);
        $this->put($to, $contents);
        $this->delete($from);
    }

    public function size($path)
    {
        $entry = $this->getModel($path);
        return $entry->filesize;
    }

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
