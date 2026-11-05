<?php

namespace app\core\helpers;

use FPDF;

class FPDFExtended extends FPDF
{

    public $W, $H, $originX;

    public const CountLines = 60;

    public $restLines;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->addCustomFonts();
    }

    private function addCustomFonts()
    {
        // calibri
        $this->AddFont('calibri','','calibri.php');
        $this->AddFont('calibri','B','calibrib.php');
        $this->AddFont('calibri','L','calibril.php');

        //roboto
        $this->AddFont('roboto','','roboto.php');
        $this->AddFont('roboto','B','robotob.php');
        $this->AddFont('roboto','L','robotol.php');
    }

    public function GetMultiCellHeightForRow(array $cols, $h, $align='J')
    {
        $maxH = 0;
        foreach ($cols as $col) {
            /**
             * $col = [
             *  'width',
             *  'text'
             * ]
             */
            $colH = $this->GetMultiCellHeight($col[0], $h, $col[1]);
            $maxH = $colH > $maxH ? $colH : $maxH;
        }
        return $maxH;
    }

    public function GetMultiCellHeight($w, $h, $txt, $border=null, $align='J') {
        // Calculate MultiCell with automatic or explicit line breaks height
        // $border is un-used, but I kept it in the parameters to keep the call
        //   to this function consistent with MultiCell()
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $height = 0;
        while($i<$nb)
        {
            // Get next character
            $c = $s[$i];
            if($c=="\n")
            {
                // Explicit line break
                if($this->ws>0)
                {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                //Increase Height
                $height += $h;
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                continue;
            }
            if($c==' ')
            {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c];
            if($l>$wmax)
            {
                // Automatic line break
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                    if($this->ws>0)
                    {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }
                    //Increase Height
                    $height += $h;
                }
                else
                {
                    if($align=='J')
                    {
                        $this->ws = ($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
                        $this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
                    }
                    //Increase Height
                    $height += $h;
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
            }
            else
                $i++;
        }
        // Last chunk
        if($this->ws>0)
        {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        //Increase Height
        $height += $h;
        return $height;
    }

    function SetDash($black=null, $white=null)
    {
        if($black!==null)
            $s=sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        else
            $s='[] 0 d';
        $this->_out($s);
    }

    function addLines($lines = 1)
    {
        if (empty($this->restLines)) {
            $this->setRestLines(self::CountLines);
        }

        $this->setRestLines($this->restLines - $lines);

        if ($this->restLines <= 0 || $this->getY() > $this->hPt*0.9) {
            $this->setRestLines(self::CountLines);
            return false;
        }

        return  true;
    }

    function setRestLines($rest)
    {
        $this->restLines = $rest;
    }

}