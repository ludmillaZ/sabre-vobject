<?php

namespace Sabre\VObject\Property;

use
    Sabre\VObject\Property,
    Sabre\VObject\Parser\MimeDir,
    Sabre\VObject\Document;

/**
 * Text property
 *
 * This object represents TEXT values.
 *
 * @copyright Copyright (C) 2007-2013 fruux GmbH. All rights reserved.
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Text extends Property {

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @param string $val
     * @return void
     */
    public function setRawMimeDirValue($val) {

        $this->setValue(MimeDir::unescapeValue($val, $this->delimiter));

    }

    /**
     * Sets the value as a quoted-printable encoded string.
     *
     * @param string $val
     * @return void
     */
    public function setQuotedPrintableValue($val) {

        $val = quoted_printable_decode($val);

        // Quoted printable only appears in vCard 2.1, and the only character
        // that may be escaped there is ;. So we are simply splitting on just
        // that.
        //
        // We also don't have to unescape \\, so all we need to look for is a ;
        // that's not preceeded with a \.
        $regex = '# (?<!\\\\) ; #x';
        $matches = preg_split($regex, $val);
        $this->setValue($val);

    }

    /**
     * Returns a raw mime-dir representation of the value.
     *
     * @return string
     */
    public function getRawMimeDirValue() {

        $val = $this->getParts();

        foreach($val as &$item) {

            $item = strtr($item, array(
                '\\' => '\\\\',
                ';'  => '\;',
                ','  => '\,',
                "\n" => '\n',
                "\r" => "",
            ));

        }

        return implode($this->delimiter, $val);

    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize() {

        // We need to kick in a special type of encoding, if it's a 2.1 vcard.
        if ($this->root->getDocumentType() !== Document::VCARD21) {
            return parent::serialize();
        }

        $val = $this->getParts();

        // Imploding multiple parts into a single value, and splitting the
        // values with ;.
        if (count($val)>1) {
            foreach($val as $k=>$v) {
                $val[$k] = str_replace(';','\;', $v);
            }
            $val = implode(';', $val);
        } else {
            $val = $val[0];
        }

        $str = $this->name;
        if ($this->group) $str = $this->group . '.' . $this->name;
        foreach($this->parameters as $param) {

            if ($param->getValue() === 'QUOTED-PRINTABLE') {
                continue;
            }
            $str.=';' . $param->serialize();

        }



        // If the resulting value contains a \n, we must encode it as
        // quoted-printable.
        if (strpos($val,"\n") !== false) {

            $str.=';ENCODING=QUOTED-PRINTABLE:';
            $lastLine=$str;
            $out = null;

            // The PHP built-in quoted-printable-encode does not correctly
            // encode newlines for us. Specifically, the \r\n sequence must in
            // vcards be encoded as =0D=OA and we must insert soft-newlines
            // every 75 bytes.
            for($ii=0;$ii<strlen($val);$ii++) {
                $ord = ord($val[$ii]);
                // These characters are encoded as themselves.
                if ($ord >= 32 && $ord <=126) {
                    $lastLine.=$val[$ii];
                } else {
                    $lastLine.='=' . strtoupper(bin2hex($val[$ii]));
                }
                if (strlen($lastLine)>=75) {
                    // Soft line break
                    $out.=$lastLine. "=\r\n ";
                    $lastLine = null;
                }

            }
            if (!is_null($lastLine)) $out.= $lastLine . "\r\n";
            return $out;

        } else {
            $str.=':' . $val;
            $out = '';
            while(strlen($str)>0) {
                if (strlen($str)>75) {
                    $out.= mb_strcut($str,0,75,'utf-8') . "\r\n";
                    $str = ' ' . mb_strcut($str,75,strlen($str),'utf-8');
                } else {
                    $out.=$str . "\r\n";
                    $str='';
                    break;
                }
            }

            return $out;


        }

    }

}
