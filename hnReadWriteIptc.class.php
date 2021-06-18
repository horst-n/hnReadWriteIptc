<?php

/**
* Class that helps with reading / writing IPTC data from / into JPEG image files
*
* @date 2021-06-18
* @version  0.1.0
*/
class hnReadWriteIptc {

    /**
     * List of valid IPTC tags (@horst)
     *
     * @var array
     *
     */
    protected $validIptcTags = array(
        '005', '007', '010', '012', '015', '020', '022', '025', '030', '035', '037', '038', '040', '045', '047', '050', '055', '060',
        '062', '063', '065', '070', '075', '080', '085', '090', '092', '095', '100', '101', '103', '105', '110', '115', '116', '118',
        '120', '121', '122', '130', '131', '135', '150', '199', '209', '210', '211', '212', '213', '214', '215', '216', '217');

    /**
    * The IPTC tag where we store our custom settings as key / value pairs
    * (serialized)
    *
    * @var string
    */
    protected $tagname = '2#215';

    /**
    * Filename of the image to read from or write to
    *
    * @var filename
    */
    protected $filename = null;

    /**
     * Result of iptcparse(), if available
     *
     * @var mixed
     *
     */
    protected $iptcRaw = null;

    public function __construct($filename) {
        $this->readImagefile($filename);
    }

    public function readTag($key) {
        if(!isset($this->iptcRaw[$this->tagname])) return null;
        $data = unserialize($this->iptcRaw[$this->tagname][0]);
        if(!is_array($data) || !isset($data[$key])) return null;
        return $data[$key];
    }

    public function writeTag($key, $value) {
        $newData = [$key => $value];
        $oldData = isset($this->iptcRaw[$this->tagname]) && is_array($this->iptcRaw[$this->tagname]) && isset($this->iptcRaw[$this->tagname][0]) && is_string($this->iptcRaw[$this->tagname][0]) && 0 < strlen($this->iptcRaw[$this->tagname][0]) ? unserialize($this->iptcRaw[$this->tagname][0]) : [];
        $data = serialize(array_merge($oldData, $newData));
        $this->iptcRaw[$this->tagname][0] = $data;
    }

    public function readImagefile($filename) {
        $this->filename = $filename;
        $imageInspector = new ImageInspector($this->filename);
        $inspectionResult = $imageInspector->inspect($this->filename, true);
        $this->iptcRaw = is_array($inspectionResult['iptcRaw']) ? $inspectionResult['iptcRaw'] : [];
    }

    public function writeIptcIntoFile($filename = null, $iptcRaw = null) {
        if(null === $filename) $filename = $this->filename;
        if(null === $iptcRaw) $iptcRaw = $this->iptcRaw;
        $content = iptcembed($this->iptcPrepareData($iptcRaw, true), $filename);
        if($content !== false) {
            $dest = $filename . '.tmp';
            if(strlen($content) == @file_put_contents($dest, $content, LOCK_EX)) {
                // on success we replace the file
                unlink($filename);
                rename($dest, $filename);
            } else {
                // it was created a temp diskfile but not with all data in it
                if(file_exists($dest)) {
                    @unlink($dest);
                    return false;
                }
            }
        }
        return true;
    }

    protected function iptcPrepareData($iptcRaw, $includeCustomTags = false) {
        $customTags = array('213', '214', '215', '216', '217');
        $iptcNew = '';
        foreach(array_keys($iptcRaw) as $s) {
            $tag = substr($s, 2);
            if(!$includeCustomTags && in_array($tag, $customTags)) continue;
            if(substr($s, 0, 1) == '2' && in_array($tag, $this->validIptcTags) && is_array($this->iptcRaw[$s])) {
                foreach($iptcRaw[$s] as $row) {
                    $iptcNew .= $this->iptcMakeTag(2, $tag, $row);
                }
            }
        }
        return $iptcNew;
    }

    protected function iptcMakeTag($rec, $dat, $val) {
        $len = strlen($val);
        if($len < 0x8000) {
            return @chr(0x1c) . @chr($rec) . @chr($dat) .
            chr($len >> 8) .
            chr($len & 0xff) .
            $val;
        } else {
            return chr(0x1c) . chr($rec) . chr($dat) .
            chr(0x80) . chr(0x04) .
            chr(($len >> 24) & 0xff) .
            chr(($len >> 16) & 0xff) .
            chr(($len >> 8) & 0xff) .
            chr(($len) & 0xff) .
            $val;
        }
    }
}
