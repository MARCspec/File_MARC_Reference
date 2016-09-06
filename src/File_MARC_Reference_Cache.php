<?php

class File_MARC_Reference_Cache implements ArrayAccess
{
    /**
     * @var array Array of fields or subfields
     */
    protected $data = [];
    /**
     * @var array Associative Array of specs
     */
    protected $spec = [];
    /**
     * @var array Associative Array of validation results
     */
    protected $valid = [];

    /**
     * Cache a spec.
     *
     * This is a cache control method for specs
     *
     * @param string|MARCspecInterface|SubSpecInterface $spec  The spec to cache
     * @param bool|null                                 $value Validation result for a subspec
     *
     * @return mixed Returns either a spec or a validation result
     */
    public function spec($spec, $value = null)
    {
        $key = "$spec";
        if (array_key_exists($key, $this->spec)) {
            return $this->spec[$key]; // return since spec is already cached
        }
        // cache a spec
        if (!is_null($value)) {
            return $this->spec[$key] = $value;
        }

        if (is_string($spec)) {
            $spec = new CK\MARCspec\MARCspec($spec);
        }

        $this->spec[$key] = $spec;
        $cmp = $this->spec[$key]->__toString();
        if ($cmp !== $key) {
            $this->spec[$cmp] = $this->spec[$key];
        }

        return $this->spec[$key];
    }

    /**
     * Set or get subspec validation.
     *
     * @param SubSpecInterface|string $subSpec The validated subspec
     * @param bool|null               $value   Validation result true or false
     *
     * @return bool|null If subspec was already validated return true or false.
     *                   Returns null, if subspec was not already validated.
     */
    public function validation($subSpec, $value = null)
    {
        $key = "$subSpec";
        if (is_null($value)) {
            if (array_key_exists($key, $this->valid)) {
                return $this->valid[$key];
            } else {
                return;
            }
        }
        $this->valid[$key] = $value;
    }

    /**
     * Set data.
     *
     * @param FieldInterface|SubfieldInterface|string $key   Datas spec
     * @param bool|null                               $value The data
     */
    protected function setData($key, $value)
    {
        $this->data[$key] = (is_array($value)) ? $value : [$value];
    }

    /**
     * Get the data.
     *
     * Extract data from $this->data depending on provided specs
     *
     * @param FieldInterface    $fieldspec    Datas field spec
     * @param SubfieldInterface $subfieldspec Datas subfield spec
     *
     * @return array[File_MARC_Field|File_MARC_Subfield] Array of data
     */
    public function getData(CK\MARCspec\FieldInterface $fieldspec, CK\MARCspec\SubfieldInterface $subfieldspec = null)
    {
        if (!$this->data) {
            return [];
        }

        $fieldBase = $fieldspec->getBaseSpec();
        if (!is_null($subfieldspec)) {
            $key = $fieldBase.$subfieldspec->getBaseSpec();
            if (array_key_exists($key, $this->data)) {
                return $this->data[$key];
            }
            $key = $fieldBase.'$'.$subfieldspec->getTag();
            if (array_key_exists($key, $this->data)) {
                return array_values($this->sliceData($key, $subfieldspec));
            }

            return [];
        }

        // subfieldspec is null
        if (array_key_exists($fieldBase, $this->data)) {
            return $this->data[$fieldBase];
        }
        $key = $fieldspec->getTag();
        if (array_key_exists($key, $this->data)) {
            return array_values($this->sliceData($key, $fieldspec));
        }

        return [];
    }

    /**
     * Get a slice from data.
     *
     * @param string                           $key  The datas key
     * @param FieldInterface|SubfieldInterface $spec Spec with slice information
     *
     * @return array[File_MARC_Field|File_MARC_Subfield]
     */
    private function sliceData($key, $spec)
    {
        $start = $spec->getIndexStart();
        if ('#' === $start) { // reverse index order
            $end = count($this->data[$key]) - 1;
            $start = $spec->getIndexEnd();
            if ('#' === $start) {
                return [$this->data[$key][$end]];
            }
            $length = $end - $start;

            return array_slice($this->data[$key], $start, $length);
        }
        $end = $spec->getIndexEnd();
        if ('#' === $end) {
            $end = count($this->data[$key]) - 1;
        }
        $length = $end - $start + 1;

        return array_slice($this->data[$key], $start, $length);
    }

    /**
     * Get referenced data content.
     *
     * @param FieldInterface|SubfieldInterface          $spec  The corresponding spec
     * @param array[File_MARC_Field|File_MARC_Subfield] $value The value to reference
     *
     * @return array[string] Array of referenced data content
     */
    public function getContents($spec, array $value = [])
    {
        if (!$value) {
            $value = $this->getData("$spec");
        }
        $substring = false;
        $charStart = null;
        $length = null;
        if ($spec->offsetExists('charStart')) {
            $substring = true;
            $charStart = $spec['charStart'];
            $length = $spec['charLength'] * 1;
            if ('#' === $charStart) {
                // negative index
                $charStart = $length * -1;
            }
        }
        array_walk(
            $value,
            function (&$val, $key) use ($substring, $charStart, $length) {
                /*
                * Convert to string
                */
                // leader
                if (is_string($val)) {
                    // value stays untouched
                } elseif ($val instanceof File_MARC_Data_Field) {
                    $val = $val->getContents();
                } else {
                    $val = $val->getData();
                }
                /*
                 * Get substring
                 */
                if ($substring) {
                    $val = substr($val, $charStart, $length);
                }
            }
        );

        return $value;
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param string                             $key   The offset to assign the value to
     * @param File_MARC_Field|File_MARC_Subfield $value The value to set
     *
     *
     * @abstracting ArrayAccess
     */
    public function offsetSet($key, $value)
    {
        $this->setData("$key", $value);
    }

    /**
     * Whether or not an offset exists.
     *
     * @param string $key An offset to check for
     *
     * @return bool
     *
     * @abstracting ArrayAccess
     */
    public function offsetExists($key)
    {
        return array_key_exists("$key", $this->data);
    }

    /**
     * Unsets an offset.
     *
     * @param string $key The offset to unset
     *
     *
     * @abstracting ArrayAccess
     */
    public function offsetUnset($key)
    {
        unset($this->data["$key"]);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param string $key The offset to retrieve
     *
     * @return array[File_MARC_Field|File_MARC_Subfield]
     *
     * @abstracting ArrayAccess
     */
    public function offsetGet($key)
    {
        $key = "$key";
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return [];
    }

    /**
     * Get protected attributes.
     *
     * @param string $name The property name
     *
     * @return array[File_MARC_Field|File_MARC_Subfield]
     */
    public function __get($name)
    {
        if ('data' == $name) {
            return $this->data;
        }
    }
}
