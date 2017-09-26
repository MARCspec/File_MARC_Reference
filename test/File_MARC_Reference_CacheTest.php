<?php
/*
* (c) Carsten Klee <mailme.klee@yahoo.de>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

use CK\MARCspec\MARCspec;
use PHPUnit\Framework\TestCase;

/**
 * @covers File_MARC_Reference_Cache
 */
class File_MARC_Reference_CacheTest extends TestCase
{
    protected $record;
    protected $cache;

    protected function setUp()
    {
        $this->cache = new File_MARC_Reference_Cache();
        if (false === ($this->record instanceof \File_MARC_Record)) {
            // Retrieve a set of MARC records from a file
            $records = new \File_MARC('test/music.mrc');

            // Iterate through the retrieved records
            $this->record = $records->next();
        }
    }

    public function testCacheASpec()
    {
        $spec = $this->cache->spec(new MARCspec('245$a'));
        $this->assertSame($spec, $this->cache->spec('245$a'));

        $spec = $this->cache->spec('245$b', new MARCspec('245$b'));
        $this->assertSame($spec, $this->cache->spec('245$b'));

        $spec = $this->cache->spec(new MARCspec('...[0-#]'));
        $spec2 = $this->cache->spec('...');
        $this->assertSame($spec, $spec2);

        $spec = $this->cache->spec('245$c[0-#]');
        $this->assertSame($spec->__toString(), $this->cache->spec('245$c')->__toString());
    }

    public function testCacheAValidation()
    {
        $spec = $this->cache->spec(new MARCspec('245$b{$a}'));
        $this->cache->validation($spec['subfields'][0]['subSpecs'][0], true);
        $this->assertTrue($this->cache->validation($spec['subfields'][0]['subSpecs'][0]));
        $this->assertTrue($this->cache->validation('245$a'));
        $this->assertNull($this->cache->validation('245$b'));
    }

    public function testCacheData()
    {
        $spec = $this->cache->spec('LDR');
        $ldr = $this->record->getLeader();
        $this->cache['LDR'] = $ldr;
        // double entry
        $this->cache[$spec['field']] = $this->record->getLeader();
        $this->assertSame(['01145ncm  2200277 i 4500'], $this->cache->getData($spec['field']));
        $data = $this->cache->data;
        $this->assertSame(['01145ncm  2200277 i 4500'], $data['LDR']);

        $wildcard = $this->cache->spec('7.0');
        $this->cache[$wildcard] = $this->record->getFields('7.0', true);
        $data = $this->cache->data;
        $this->assertSame(2, count($data));
        $this->assertSame(4, count($data['7.0']));

        unset($this->cache['LDR']);
        $this->assertFalse(isset($this->cache['LDR']));
    }

    public function testCacheData2()
    {
        $spec = $this->cache->spec('7.0$a');
        $fields = $this->record->getFields('7.0', true);
        $subfields = [];
        foreach ($fields as $field) {
            array_push($subfields, $field->getSubfield('a'));
        }
        $this->cache['7.0$a'] = $subfields;
        $data = $this->cache->getData($spec['field'], $spec['subfields'][0]);
        $this->assertSame(4, count($data));
        $slice = $this->cache->spec('7.0$a[1-2]');
        $slice_data = $this->cache->getData($slice['field'], $slice['subfields'][0]);
        $this->assertSame(2, count($slice_data));
        $values = $this->cache->getContents($slice['subfields'][0], $slice_data);
        $this->cache['7.0'] = $fields;
        $spec['field']->setIndexStartEnd(1);
        $this->assertSame([$this->cache['7.0'][1]], $this->cache->getData($spec['field']));
    }

    public function testGetContents()
    {
        $spec = $this->cache->spec('245/#-1');
        $field = $this->record->getField('245');
        $this->cache["$spec"] = $field;
        $this->assertSame(['--'], $this->cache->getContents($spec['field'], $this->cache["$spec"]));
    }

    public function testGetContents2()
    {
        $spec = $this->cache->spec('245$a/0-3');
        $field = $this->record->getField('245');
        $sf = $field->getSubfield('a');
        $this->cache[$spec['subfields'][0]->__toString()] = $sf;
        // an emty one
        $this->cache[$spec['subfields'][0]->__toString()] = [];

        $this->assertSame(['The '], $this->cache->getContents($spec['subfields'][0], [$sf]));
    }
}
