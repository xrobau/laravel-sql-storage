<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * This file (will be) auto-created by the laravel-sql-storage provider.
 */
return new class extends Migration
{
    protected string $mconnection = 'mysql';
    protected string $mtablename = 'sqlstorage';
    protected int $partitions = 256;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tablename = $this->mtablename;
        $blobtable = $this->mtablename . "_blobstore";
        $tablesql = "create table $tablename (
            uuid char(64) not null,
            filename char(128) not null,
            path char(128) default '',
            filesize int default null,
            metadata json,
            created_at timestamp NULL DEFAULT NULL,
            updated_at timestamp NULL DEFAULT NULL,
            unique key p (uuid,filename,path))
            partition by key(uuid) partitions " . $this->partitions;

        $blobsql = "create table $blobtable (
            uuid char(64) not null,
            filename char(128) not null,
            path char(128) default '',
            chunkid int not null,
            metadata json,
            contents blob default null,
            created_at timestamp NULL DEFAULT NULL,
            updated_at timestamp NULL DEFAULT NULL,
            unique key p (uuid,filename,path,chunkid))
            partition by key(uuid) partitions " . $this->partitions;

        $s = Schema::connection($this->mconnection);
        if (!$s) {
            throw new \Exception("Tried to get a connection '$this->mconnection', but it failed");
        }
        $pdo = $s->getConnection()->getPdo();
        if (!$s->hasTable($tablename)) {
            $pdo->query($tablesql);
        }
        if (!$s->hasTable($blobtable)) {
            $pdo->query($blobsql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tablename = $this->mtablename;
        $blobtable = $this->mtablename . "_blobstore";
        Schema::connection($this->mconnection)->dropIfExists($tablename);
        Schema::connection($this->mconnection)->dropIfExists($blobtable);
    }
};
