# Laravel Storage using MySQL

This was created because I needed to store 10+m files, linked to 1m+ identifiers,
and using NFS was unwieldy.

I threw this together in an afternoon. It's probably missing a bunch of stuff, including
the ability to list directories.

Listing files is incomplete, but will be added shortly (hint: FileModel with a trailing
slash on the path)

## Basic Instructions

### Install

`composer require xrobau/laravel-sql-storage`

### Run the migrations

`php artisan migrate`

It will make two tables - sqlstorage and sqlstorage_blobstore.

## Usage

```
// Everything is related to an individual uuid. Think of this as
// the base folder.
$uuid = 'a3b04866-99b4-4edd-a85f-b12727bdd2ca';

// The @var is just an IDE Hint
/** @var SqlStorageDriver $s */
$s = Storage::disk('database');
// If you don't set 'usingUuid' it'll throw.
$s->usingUuid($uuid);

// This is the filename you want to mess with
$filename = "this.is.fake.txt";
// Set it to some random contents
$contents = str_repeat(str_repeat("a", 100) . str_repeat("b", 100), 100);
$s->put($filename, $contents);

// Now you can test it
if (!$s->exists($filename)) {
    print "$filename does not exist?\n";
    exit;
};

$out = $s->get($filename);
if ($out !== $contents) {
    print "Output didn't match what was put in?\nOut: '$out'\nOrig: '$contents'\n";
    exit;
}

print "Size is " . $s->size($filename) . "\n";
$m = $s->getModel($filename);
print "Model is: " . json_encode($m) . "\n";
$s->delete($filename);
```
