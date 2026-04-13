<?php
namespace App\Services\ReportServices;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
class MpdfService
{
    private $mpdf;

    public function __construct()
    {
        
    }

    public function getMpdf()
    {
        return $this->mpdf;
    }

    public function setMpdf(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
    }
           
    private static function mpdfconfig(){

    return [

        'margin_left' => 20,

        'margin_right' => 15,

        'margin_top' => 30,

        'margin_bottom' => 25,

        'margin_header' => 3,

        'margin_footer' => 10,

        //'orientation' => 'L',

        'mode' => 'utf-8',

        // 'debug'=>true,

        'format' => [210, 297] //A4

    ];

    }



private static function mpdfconfig_land(){
return [

'margin_left' => 20,

'margin_right' => 30,

'margin_top' => 40,

'margin_bottom' => 25,

'margin_header' => 3,

'margin_footer' => 10,

'orientation' => 'L',

'mode' => 'utf-8',

'format' => [210,297]

];

}

private static function mpdfconfig_dot(){

return [

'margin_left' => 20,

'margin_right' => 15,

'margin_top' => 25,

'margin_bottom' => 25,

'margin_header' => 10,

'margin_footer' => 10,

//'orientation' => 'L',

'mode' => 'utf-8',

'format' => [228.6, 177.8]

];

}
public function mpdf($name,$config = ''){
ini_set("pcre.backtrack_limit", "50000000");
ini_set('memory_limit', '512M');
if($config == 'dot') {

$config = self::mpdfconfig_dot();

} else if($config == 'L') {

$config = self::mpdfconfig_land();

} else {

$config = self::mpdfconfig();

}

$this->mpdf = new \Mpdf\Mpdf($config);

$this->mpdf->WriteHTML(File::get(public_path()."/assets/ahits/css/pdf.min.css"),\Mpdf\HTMLParserMode::HEADER_CSS);

// $this->mpdf->SetProtection(array('print'));

$this->mpdf->SetTitle("$name");
$this->mpdf->SetAuthor("Arrowhead IT Solutions");

}
}