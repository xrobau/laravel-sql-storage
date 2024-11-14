<?php

namespace xrobau\LaravelSqlStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileBlobModel extends Model
{
    protected $table = 'sqlstorage_blobstore';
    public $timestamps = true;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKeys = ['uuid', 'filename', 'path', 'chunkid'];
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];

    public static function storeContent(array $attributes, array $chunks)
    {
        DB::beginTransaction();
        $retarr = ['attributes' => $attributes, 'updated' => [], 'deleted' => 0, 'chunks' => [], 'startmsec' => microtime(true)];
        $params = $attributes;
        // Just to be on the safe side, there should ALWAYS be chunks.
        $i = -1;
        foreach ($chunks as $i => $chunk) {
            $updated = false;
            $params['chunkid'] = $i;
            $o = self::where($params)->first();
            if (!$o) {
                $o = new static($params);
            }
            $ometadata = $o->metadata ?? ['chunkhash' => '', 'chunklen' => 0];
            if (!is_array($ometadata)) {
                throw new \Exception("metadata is not an array? " . serialize($ometadata));
            }
            $chunkhash = hash('sha256', $chunk);
            $chunklen = strlen($chunk);
            if ($chunkhash !== $ometadata['chunkhash'] || $chunklen !== $ometadata['chunklen']) {
                // It needs to be updated
                $updated = true;
                $o->contents = $chunk;
                $o->metadata = ['chunkhash' => $chunkhash,  'chunklen' => $chunklen];
            }
            if ($updated) {
                $o->save();
                $retarr['updated'][$i] = $o;
            }
            // $retarr['chunks'][$i] = $o;
        }
        // Nowe we have the maximum number of chunks, delete any that have a higher chunkid
        // than $i
        $retarr['deleted'] = self::where([
            'uuid' => $attributes['uuid'],
            'filename' => $attributes['filename'],
            'path' => $attributes['path']
        ])->where('chunkid', '>', $i)->delete();
        DB::commit();
        $retarr['endmsec'] = microtime(true);
        return $retarr;
    }

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('uuid', '=', $this->getAttribute('uuid'))
            ->where('filename', '=', $this->getAttribute('filename'))
            ->where('path', '=', $this->getAttribute('path'))
            ->where('chunkid', '=', $this->getAttribute('chunkid'));
        return $query;
    }

    // Not wonderfully happy with this, as the whole file has to be
    // loaded into memory, but it'll have to do for the moment
    public static function getContent(FileModel $f): string
    {
        $contents = "";
        $params = ['uuid' => $f->uuid, 'filename' => $f->filename, 'path' => $f->path];
        self::where($params)->orderBy('chunkid')->each(function ($r) use (&$contents) {
            $contents .= $r->contents;
        });
        return $contents;
    }
}
