<?php
/*
* (c) Carsten Klee <mailme.klee@yahoo.de>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
//namespace CK\File_MARC_Reference\Test;

//use CK\File_MARC_Reference\File_MARC_Reference;
use CK\MARCspec\MARCspec;

class File_MARC_ReferenceTest extends \PHPUnit_Framework_TestCase
{
    protected $record;
    
    protected function setUp()
    {
        // Retrieve a set of MARC records from a file
        $records = new \File_MARC('test/music.mrc');
        
        // Iterate through the retrieved records
        $this->record = $records->next();
    }
    
    public function testInstanciateFile_MARC_Record()
    {
        $this->assertInstanceOf('File_MARC_Record', $this->record);
    }
    
    public function testInstanciateMARCspecInterface()
    {
        $Spec = new MARCspec('245');
        $this->assertInstanceOf('CK\MARCspec\MARCspecInterface', $Spec);
    }
    
    public function testInstanciateFile_MARC_Reference()
    {
        $Reference = new File_MARC_Reference('245',$this->record);
        $this->assertInstanceOf('File_MARC_Reference', $Reference);
    }
    
    public function testGetAll()
    {
        $Reference = new File_MARC_Reference('...',$this->record);
        $this->assertSame(21, count($Reference->data));
        
    }
    
    public function testGetLeader()
    {
        $Reference = new File_MARC_Reference('LDR',$this->record);
        $this->assertSame('01145ncm  2200277 i 4500', $Reference->content[0]);
    }
    
    public function testGetLeaderSubstring()
    {
        $Reference = new File_MARC_Reference('LDR/0-3',$this->record);
        $this->assertSame('0114', $Reference->content[0]);
    }
    
    public function testGetLeaderSubstringReverse()
    {
        $Reference = new File_MARC_Reference('LDR/#-3',$this->record);
        $this->assertSame('4500', $Reference->content[0]);
    }
    
    public function testGetSingle()
    {
        $Reference = new File_MARC_Reference('245',$this->record);
        $this->assertInstanceOf('File_MARC_Field', $Reference->data[0]);
        $this->assertSame('245 04 _aThe Modern Jazz Quartet :
       _bThe legendary profile. --', $Reference->data[0]->__toString());
    }
    
    public function testGetRepeatable()
    {
        $Reference = new File_MARC_Reference('700[1-2]',$this->record);
        $this->assertInstanceOf('File_MARC_Data_Field', $Reference->data[0]);
        $this->assertSame(2,count($Reference->data));
    }
    
    public function testGetRepeatableWildCardSubstring()
    {
        $_substrings = ['00','AA','20','80'];
        
        $Reference = new File_MARC_Reference('00./0-1',$this->record);
        $this->assertSame($_substrings, $Reference->content);
    }
    
    public function testGetSingleSubfield()
    {
        $Reference = new File_MARC_Reference('245$a',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
    }
    
    public function testGetMultiSubfields()
    {
        $Reference = new File_MARC_Reference('245$a-b',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        $this->assertSame("The legendary profile. --", $Reference->content[1]);
    }
    
    public function testGetSubfieldsOfRepeatedField()
    {
        $_contents = ['Lewis, John,','Jackson, Milt.','Jackson, Milt.'];
        $Reference = new File_MARC_Reference('700$a',$this->record);
        $this->assertSame($_contents, $Reference->content);
    }
    
    public function testGetSubfieldByIndex()
    {
        $newSubfield = new \File_MARC_Subfield('a','a test');
        $field = $this->record->getField('245');
        $field->appendSubfield($newSubfield);
        
        $Reference = new File_MARC_Reference('245$a[1]',$this->record);
        $this->assertSame('a test', $Reference->content[0]);
    }
    
    public function testSubSpecsComparisonString()
    {
        $Reference = new File_MARC_Reference('245$a[0]{650$a=\Jazz.}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{650$a!=\Techno.}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{650$a~\Jazz}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{650$a!~\foo}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]); 
        
        $Reference = new File_MARC_Reference('245$a[0]{\bar!=\foo}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{\foobar~\foo}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{?\foo}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{!\foo}',$this->record);
        $this->assertNull($Reference->content[0]);
    }

    public function testSubSpecsExists()
    {
        $Reference = new File_MARC_Reference('245$a[0]{?260$c}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
    }
    
    public function testSubSpecsNotExists()
    {
        $Reference = new File_MARC_Reference('245$a[0]{!501$a}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
    }
    
    public function testSubSpecsRepeated()
    {
        $Reference = new File_MARC_Reference('245$a[0]{050$a}{050$f}',$this->record);
        $this->assertNull($Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{050$a}{050$b}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
    }
    
    public function testSubSpecsChained()
    {
        $Reference = new File_MARC_Reference('245$a[0]{050$a|050$f}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
       
        $Reference = new File_MARC_Reference('245$a[0]{050$a|050$b}{050$d}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
    }

    public function testSubSpecsData()
    {
        $Reference = new File_MARC_Reference('245$a[0]{050~050}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);
        
        $Reference = new File_MARC_Reference('245$a[0]{050~\M}',$this->record);
        $this->assertSame("The Modern Jazz Quartet :", $Reference->content[0]);

    }

    public function testAllSubfields()
    {
        $Reference = new File_MARC_Reference('...$a',$this->record);
        foreach( $Reference->data as $key => $data)
        {
            $this->assertSame('a',$data->getCode());
            $this->assertSame($Reference->content[$key],$data->getData());
            
        }
    }
    
    public function testDependencies()
    {
        $newSubfields = array(new File_MARC_Subfield('a','003100'),new File_MARC_Subfield('a','001839'));
        $newData = new File_MARC_Data_Field('306',$newSubfields);
        $this->record->appendField($newData);
        
        $newControl1 = new File_MARC_Control_Field('007',"ou");
        $newControl2 = new File_MARC_Control_Field('007',"vf#caahos");
        $this->record->appendField($newControl1);
        $this->record->appendField($newControl2);

        $reference = new File_MARC_Reference('306$a{007/0=\m|007/0=\s|007/0=\v}',$this->record);

        $this->assertSame("003100",$reference->content[0]);
        $this->assertSame("001839",$reference->content[1]);
        
    }
    
    public function testSubfieldSubstrings()
    {
        $Reference = new File_MARC_Reference('245$a[0]/#-3',$this->record);
        $this->assertSame("et :", $Reference->content[0]);
    }

}