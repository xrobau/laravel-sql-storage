<?php

namespace xrobau\LaravelSqlStorage\Models;

use Illuminate\Database\Eloquent\Model;

class FileModel extends Model
{
    protected $table = 'sqlstorage';
    public $timestamps = true;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKeys = ['uuid', 'filename', 'path'];
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('uuid', '=', $this->getAttribute('uuid'))
            ->where('filename', '=', $this->getAttribute('filename'))
            ->where('path', '=', $this->getAttribute('path'));
        return $query;
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     * This is extended from Illuminate/Database/Query/Builder
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return self
     */
    public static function updateOrInsert(array $attributes, array $values = []): static
    {
        $changed = false;
        $chunksize = 65000;
        if (!empty($attributes['contents'])) {
            throw new \Exception("Can not use contents in attributes");
        }
        // Do we have a current one?
        $current = self::where($attributes)->first();
        if (!$current) {
            $current = new FileModel($attributes);
            $changed = true;
        }
        $metadata = $current->metadata ?? ['contenthash' => 'null'];
        // Take our contents from values, if it exists (why wouldn't it?)
        $contents = $values['contents'] ?? false;
        unset($values['contents']);
        if ($contents !== false) {
            // We have contents to set
            $contenthash = hash('sha256', $contents);
            if ($metadata['contenthash'] !== $contenthash) {
                // It needs to be updated
                $changed = true;
                $current->filesize = strlen($contents);
                $chunks = str_split($contents, $chunksize);
                $metadata['chunkcount'] = count($chunks);
                $metadata['contenthash'] = $contenthash;
                $current->metadata = $metadata;
                FileBlobModel::storeContent($attributes, $chunks);
                // print json_encode([$result, $current]) . "\n";
            }
        }
        foreach ($attributes as $a => $v) {
            if ($current->$a !== $v) {
                // print "Mismatch $a should be $v but is " . $current->$a . "\n";
                $changed = true;
                $current->$a = $v;
            }
        }
        if ($changed) {
            // print "Changed\n";
            $current->save();
        }
        return $current;
    }

    /**
     * This overrides the get for 'contents', which is a collection
     * of FileBlobModels. Anything else is passed up to the parent
     * model.
     */
    public function __get($key)
    {
        if ($key == 'contents') {
            return FileBlobModel::getContent($this);
        }
        return parent::__get($key);
    }

    public static function purge(array $params)
    {
        $retarr = ['fm' => 0, 'fbm' => 0];
        $retarr['fm'] = self::where($params)->delete();
        $retarr['fbm'] = FileBlobModel::where($params)->delete();
        return $retarr;
    }
}
