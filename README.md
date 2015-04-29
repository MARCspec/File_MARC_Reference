<<<<<<< HEAD
#File_MARC_Reference

File_MARC_Reference is an extension to the famous MARC parser for PHP [File_MARC](http://pear.php.net/package/File_MARC). With File_MARC_Reference you can use [MARCspec](http://marcspec.github.io/MARCspec) as an unified way to access MARC data. Besides it simplifies File_MARC a lot. 

# Installation

Installation can be done by using [composer](https://getcomposer.org/doc/00-intro.md).

Create a composer.json file with the content

```json
{
    "require": {
        "ck/File_MARC_Reference": "dev-master"
    }
}
```

in the root of your project. And install File_MARC_Reference via command line: *composer install*

Along with File_MARC_Reference the following libraries will be installed:

- [File_MARC](http://pear.php.net/package/File_MARC) (require-dev)
- [Validate_ISPN](http://pear.php.net/package/Validate_ISPN) (require-dev)
- [PEAR_Exception](http://pear.php.net/package/PEAR_Exception) (require-dev)
- [php-marc-spec](https://github.com/MARCspec/php-marc-spec) (require)
- [phpunit](https://github.com/sebastianbergmann/phpunit) (require-dev)

If you already installed File_MARC you can skip its installation with: *composer install --no-dev*. This way have to include File_MARC manually:

```php
require 'File/MARC.php';
```

# Usage

First require autoload.php

```php
<?php
require("vendor/autoload.php");
```

Reading MARC 21 data from a file or string stays the same (see [File_MARC - Reading MARC data](http://pear.php.net/manual/en/package.fileformats.file-marc.reading.php)):

```php
// Retrieve a set of MARC records from a file
$records = new File_MARC('data.mrc');

// Iterate through the retrieved records
while ($record = $records->next())
{
 //...
}
```

Now if you want to reference data using a MARCspec you have to initialize a new File_MARC_Reference:

```php
// Retrieve a set of MARC records from a file
$records = new File_MARC('data.mrc');

// Iterate through the retrieved records
while ($record = $records->next())
{
    $reference = new File_MARC_Reference('245$a',$record);
}
```

After you referenced the data three public attributes are available:

```php
$reference = new File_MARC_Reference('245$a',$record);

// attribute data
$subfield = $reference->data[0];
print get_class($subfield);               // File_MARC_Subfield
print $subfield->getCode();               // a

// attribute content
print $reference->content[0];             // prints content of subfield a of field 245

// attribute cache
$field = $reference->cache['245'][0];
print get_class($field);                  // File_MARC_Data_Field
$subfield = $reference->cache['245$a'];
print get_class($subfield);               // File_MARC_Subfield
```

Let's see how we check dependencies with File_MARC if you have a task like: Reference to content of subfield "a" of field 306 if character with index position "0" of field 007 is either "m", "s" or "v".

Instead of writing:

```php
$fields_007 = $record->getFields('007');

$field_306 = $record->getField('306');

$subfields_a = false;

foreach($fields_007 as $field_007)
{
    $firstChar = substr($field_007->getData(),0,1);
    
    if(strpbrk($firstChar,"msv"))
    {
        $subfields_a = $field_306->getSubfields('a');
        break;
    }
}

if($subfields_a)
{
    foreach($subfields_a as $subfield_a)
    {
        echo $subfield_a->getData()."\n";
    }
}

```

Now the same task with File_MARC_Reference:

```php

    $reference = new File_MARC_Reference('306$a{007/0=\m|007/0=\s|007/0=\v}',$record);
    
    if($reference->content)
    {
        foreach($reference->content as $subfield_a)
        {
            echo $subfield_a."\n";
        }
    }
```
    
=======
# File_MARC_Reference
>>>>>>> c8f4420018ee8da515f46d66512c8a466fa2677e
