<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\support\Facades\Hash;
use Illuminate\support\Facades\Validator;
use App\Models\User;
use App\Models\Ot_one;
use PDF;
use setasign\Fpdi\Fpdi;
use App\Models\Budget_year;
// use Illuminate\Support\Facades\File;
use DataTables;
use Intervention\Image\ImageManagerStatic as Image;
// use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\OtExport;
// use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Department;
use App\Models\Departmentsub;
use App\Models\Departmentsubsub;
use App\Models\Position;
use App\Models\Product_spyprice;
use App\Models\Products;
use App\Models\Products_type;
use App\Models\Product_group;
use App\Models\Product_unit;
use App\Models\Products_category;
use App\Models\Article;
use App\Models\Product_prop;
use App\Models\Product_decline;
use App\Models\Department_sub_sub;
use App\Models\Aipn_stm;
use App\Models\Status;
use App\Models\Aipn_ipdx;
use App\Models\Aipn_ipop;
use App\Models\Aipn_session;
use App\Models\Aipn_billitems;
use App\Models\Aipn_ipadt;
use App\Models\Check_sit;
use App\Models\Stm;
use App\Models\D_aipn_main;
use App\Models\D_claim;
use App\Models\D_aipadt;
use App\Models\D_aipdx;
use App\Models\D_aipop;
use App\Models\D_abillitems;
use App\Models\D_aipn_session;
use App\Models\Ssop_billtran;
use App\Models\Ssop_billitems;
use App\Models\Claim_ssop;
use App\Models\Claim_sixteen_dru;
use App\Models\claim_sixteen_adp;
use App\Models\Claim_sixteen_cha;
use App\Models\Claim_sixteen_cht;
use App\Models\Claim_sixteen_oop;
use App\Models\Claim_sixteen_odx;
use App\Models\Claim_sixteen_orf;
use App\Models\Claim_sixteen_pat;
use App\Models\Claim_sixteen_ins;
use App\Models\Claim_temp_ssop;
use App\Models\Claim_sixteen_opd;
use Auth;
use ZipArchive;
use Storage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use PhpParser\Node\Stmt\If_;
use Stevebauman\Location\Facades\Location;
use SoapClient;
use SplFileObject;
use File;
use Illuminate\Filesystem\Filesystem;


class AipnController extends Controller
{
    public function aipn(Request $request)
    {
        $startdate = $request->datepicker;
        $enddate = $request->datepicker2;
        $date = date('Y-m-d');

        $data['d_aipn_main'] = DB::connection('mysql')->select(
            'SELECT m.d_aipn_main_id,m.vn,m.an,m.hn,p.cid,m.dchdate,m.debit,concat(p.pname,p.fname," ",p.lname) ptname
            FROM d_aipn_main m
            LEFT OUTER JOIN hos.patient p on p.hn = m.hn 
            GROUP BY m.an
            '
        );
        $data['d_aipadt'] = DB::connection('mysql')->select('SELECT * FROM d_aipadt');
        $data['d_aipdx'] = DB::connection('mysql')->select('SELECT * FROM d_aipdx');
        $data['d_aipop'] = DB::connection('mysql')->select('SELECT * FROM d_aipop');
        $data['d_abillitems'] = DB::connection('mysql')->select('SELECT * FROM d_abillitems');

        $data['d_adispensing'] = DB::connection('mysql')->select('SELECT * FROM d_adispensing');
        $data['d_adispenseditems'] = DB::connection('mysql')->select('SELECT * FROM d_adispenseditems');

        return view('aipn.aipn', $data, [
            'startdate'                => $startdate,
            'enddate'                  => $enddate,

        ]);
    }
    public function aipn_main(Request $request)
    {
        $startdate = $request->datepicker;
        $enddate = $request->datepicker2;
        $date = date('Y-m-d');
        $data_main_ = DB::connection('mysql2')->select(' 
                SELECT
                i.an,i.vn,a.hn,p.cid,i.regdate,i.regtime,i.dchdate,i.dchtime,i.pttype,concat(p.pname,p.fname," ",p.lname) ptname
                ,pt.hipdata_code,a.income,a.income-a.rcpt_money-a.discount_money as debit
                FROM ipt i
                LEFT OUTER JOIN patient p on p.hn=i.hn 
                LEFT OUTER JOIN an_stat a on a.an=i.an 
                LEFT OUTER JOIN pttype pt on pt.pttype=i.pttype 
                LEFT OUTER JOIN opitemrece op on op.an=i.an 
                LEFT OUTER JOIN s_drugitems d on d.icode=op.icode 
                WHERE i.dchdate BETWEEN "' . $startdate . '" and "' . $enddate . '"
                AND i.pttype IN("A7")
                group by i.an   
        ');
        $iduser = Auth::user()->id;
        D_aipn_main::truncate();
        D_aipadt::truncate();
        D_aipdx::truncate();
        D_aipop::truncate();
        D_abillitems::truncate();
        foreach ($data_main_ as $key => $value) {
            D_aipn_main::insert([
                'vn'                => $value->vn,
                'hn'                => $value->hn,
                'an'                => $value->an,
                'dchdate'           => $value->dchdate,
                'debit'             => $value->debit
            ]);
            $check = D_claim::where('an', $value->an)->where('nhso_adp_code', 'AIPN')->count();
            if ($check > 0) {
                # code...
            } else {
                D_claim::insert([
                    'vn'                => $value->vn,
                    'hn'                => $value->hn,
                    'an'                => $value->an,
                    'cid'               => $value->cid,
                    'pttype'            => $value->pttype,
                    'ptname'            => $value->ptname,
                    'dchdate'           => $value->dchdate,
                    'hipdata_code'      => $value->hipdata_code,
                    // 'qty'               => $value->qty,
                    'sum_price'         => $value->debit,
                    'type'              => 'IPD',
                    'nhso_adp_code'     => 'AIPN',
                    'claimdate'         => $date,
                    'userid'            => $iduser,
                ]);
            }
        }

        return response()->json([
            'status'    => '200'
        ]);
    }

    public function aipn_process(Request $request)
    {
        $data_aipn = DB::connection('mysql')->select('SELECT vn,an from d_aipn_main');
        $iduser = Auth::user()->id;
        D_aipadt::truncate();
        D_aipdx::truncate();
        D_aipop::truncate();
        D_abillitems::truncate();

        foreach ($data_aipn as $key => $va1) {
            //D_aipadt
            $aipn_data = DB::connection('mysql2')->select('   
                    SELECT
                    i.an,  
                    i.an as AN,i.hn as HN,"0" as IDTYPE 
                    ,pt.cid as PIDPAT
                    ,pt.pname as TITLE
                    ,concat(pt.fname," ",pt.lname) as NAMEPAT 
                    ,pt.birthday as DOB
                    ,a.sex as SEX
                    ,pt.marrystatus as MARRIAGE
                    ,pt.chwpart as CHANGWAT
                    ,pt.amppart as AMPHUR
                    ,pt.citizenship as NATION
                    ,"C" as AdmType
                    ,"O" as AdmSource
                    ,i.regdate as DTAdm_d
                    ,i.regtime as DTAdm_t
                    ,i.dchdate as DTDisch_d
                    ,i.dchtime as DTDisch_t 
                    ,"0" AS LeaveDay                
                    ,i.dchstts as DischStat
                    ,i.dchtype as DishType
                    ,"" as AdmWt
                    ,i.ward as DishWard
                    ,sp.nhso_code as Dept
                    ,ptt.hipdata_code maininscl
                    ,i.pttype
                    ,concat(i.pttype,":",ptt.name) pttypename 
                    ,"10702" HMAIN
                    ,"IP" as ServiceType
                    from ipt i
                    LEFT OUTER JOIN patient pt on pt.hn=i.hn
                    LEFT OUTER JOIN ptcardno pc on pc.hn=pt.hn and pc.cardtype="02"
                    LEFT OUTER JOIN an_stat a on a.an=i.an
                    LEFT OUTER JOIN spclty sp on sp.spclty=i.spclty
                    LEFT OUTER JOIN pttype ptt on ptt.pttype=i.pttype
                    LEFT OUTER JOIN pttype_eclaim ec on ec.code=ptt.pttype_eclaim_id 
                    LEFT OUTER JOIN opitemrece oo on oo.an=i.an
                    LEFT OUTER JOIN income inc on inc.income=oo.income
                    LEFT OUTER JOIN s_drugitems d on d.icode=oo.icode 
                    WHERE i.an IN("' . $va1->an . '")                   
                    AND ptt.pttype IN("A7","s7","14")
                    group by i.an 
    
            ');
            foreach ($aipn_data as $key => $value) {
                D_aipadt::insert([
                    'AN'             => $value->AN,
                    'HN'             => $value->HN,
                    'IDTYPE'         => $value->IDTYPE,
                    'PIDPAT'         => $value->PIDPAT,
                    'TITLE'          => $value->TITLE,
                    'NAMEPAT'        => $value->NAMEPAT,
                    'DOB'            => $value->DOB,
                    'SEX'            => $value->SEX,
                    'MARRIAGE'       => $value->MARRIAGE,
                    'CHANGWAT'       => $value->CHANGWAT,
                    'AMPHUR'         => $value->AMPHUR,
                    'NATION'         => $value->NATION,
                    'AdmType'        => $value->AdmType,
                    'AdmSource'      => $value->AdmSource,
                    'DTAdm_d'        => $value->DTAdm_d,
                    'DTAdm_t'        => $value->DTAdm_t,
                    'DTDisch_d'      => $value->DTDisch_d,
                    'DTDisch_t'      => $value->DTDisch_t,
                    'LeaveDay'       => $value->LeaveDay,
                    'DischStat'      => $value->DischStat,
                    'DishType'       => $value->DishType,
                    'AdmWt'          => $value->AdmWt,
                    'DishWard'       => $value->DishWard,
                    'Dept'           => $value->Dept,
                    'HMAIN'          => $value->HMAIN,
                    'ServiceType'    => $value->ServiceType
                ]);
            }

            //D_abillitems
            $aipn_billitems = DB::connection('mysql3')->select('   
                    SELECT  i.an,
                    i.an as AN,"" as sequence                            
                    ,i.regdate as DTAdm_d
                    ,i.regtime as DTAdm_t
                    ,i.dchdate as ServDate
                    ,i.dchtime as ServTime 
                    ,case 
                    when oo.item_type="H" then "04"
                    else zero(inc.income) end BillGr 
                    
                    ,inc.income as BillGrCS 
                                                    
                    ,ifnull(case  
                    when inc.income in (02) then d.nhso_adp_code
                    when inc.income in (03,04) then dd.billcode
                    when inc.income in (06,07) then d.nhso_adp_code
                    else d.nhso_adp_code end,"") CSCode

                    ,ifnull(case  
                    when inc.income in (03,04) then dd.tmt_tmlt
                    when inc.income in (06,07) then dd.tmt_tmlt
                    else "" end,"") STDCode

                    ,ifnull(case                 
                    when inc.income in (03,04) then "TMT"
                    when inc.income in (06,07) then "TMLT"
                    else "" end,"") CodeSys

                    ,oo.icode as LCCode
                    ,concat_ws("",d.name,d.strength) Descript
                    ,sum(oo.qty) as QTY
                    ,oo.UnitPrice as pricehos
                    ,dd.UnitPrice as pricecat
                    ,sum(oo.sum_price) ChargeAmt_ 
                    ,dd.tmt_tmlt 
                    ,inc.income

                    ,case 
                    when oo.paidst in ("01","03") then "T"
                    else "D" end ClaimCat

                    ,"0" as ClaimUP
                    ,"0" as ClaimAmt
                    ,i.dchdate
                    ,i.dchtime
                    ,sum(if(oo.paidst="04",sum_price,0)) Discount    
                    from ipt i
                    left outer join opitemrece oo on oo.an=i.an
                    left outer join an_stat a on a.an=i.an
                    left outer join patient pt on i.hn=pt.hn
                    left outer join income inc on inc.income=oo.income
                
                    left outer join s_drugitems d on oo.icode=d.icode
                    left join claim.aipn_drugcat_labcat dd on dd.icode=oo.icode	
                    left join claim.aipn_labcat_sks ls on ls.lccode=oo.icode
                    left join claim.aipn_drugcat_sks dks on dks.hospdcode=oo.icode

                    WHERE i.an IN("' . $va1->an . '")                        
                    and oo.qty<>0
                    and oo.UnitPrice<>0  
                    and inc.income NOT IN ("02","22" )      
                    group by oo.icode
                    order by i.an desc
            ');
            $i = 1;
            foreach ($aipn_billitems as $key => $val_bill) {
                // $codesys = $val_bill->BillGr;
                $cs_ = $val_bill->BillGrCS;
                $cs = $val_bill->CSCode;
                // $billcs = $val_bill->BillGrCS; 

                if ($cs_ == '03') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '02') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '06') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '04') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '07') {
                    $csys = $val_bill->CodeSys;
                } else {
                    $csys = '';
                }

                if ($cs == 'XXXX') {
                    $cs_code = '';
                } elseif ($cs == 'XXXXX') {
                    $cs_code = '';
                } elseif ($cs == 'XXXXXX') {
                    $cs_code = '';
                    // }elseif ($cs == '04') {
                    //     $cs_ = '';
                } else {
                    $cs_code = $val_bill->CSCode;
                }

                D_abillitems::insert([
                    'AN'                => $val_bill->AN,
                    'sequence'          => $i++,
                    'ServDate'          => $val_bill->ServDate,
                    'ServTime'          => $val_bill->ServTime,
                    'BillGr'            => $val_bill->BillGr,
                    'BillGrCS'          => $cs_,
                    'CSCode'            => $cs_code,
                    'LCCode'            => $val_bill->LCCode,
                    'Descript'          => $val_bill->Descript,
                    'QTY'               => $val_bill->QTY,
                    'UnitPrice'         => $val_bill->pricehos,
                    'ChargeAmt'         => $val_bill->QTY * $val_bill->pricehos,
                    'ClaimSys'          => "SS",
                    'CodeSys'           => $csys,
                    'STDCode'           => $val_bill->STDCode,
                    'Discount'          => "0.0000",
                    'ProcedureSeq'      => "0",
                    'DiagnosisSeq'      => "0",
                    'DateRev'           => $val_bill->ServDate,
                    'ClaimCat'          => $val_bill->ClaimCat,
                    'ClaimUP'           => $val_bill->ClaimUP,
                    'ClaimAmt'          => $val_bill->ClaimAmt
                ]);
            }

            //D_aipop
            $aipn_ipop = DB::connection('mysql3')->select('   
                SELECT
                    i.an as AN,"" as sequence,"ICD9CM" as CodeSys 
                    ,cc.icd9 as Code,icdname(cc.icd9) as Procterm,doctorlicense(cc.doctor) as DR                        
                    ,date_format(if(opdate is null,caldatetime(regdate,regtime),caldatetime(opdate,optime)),"%Y-%m-%dT%T") as DateIn
                    ,date_format(if(enddate is null,caldatetime(regdate,regtime),caldatetime(enddate,endtime)),"%Y-%m-%dT%T") as DateOut
                    ," " as Location
                    from ipt i
                    join iptoprt cc on cc.an=i.an
                    WHERE i.an IN("' . $va1->an . '")  
                    group by cc.icd9
            ');
            $i = 1;
            foreach ($aipn_ipop as $key => $ipop) {
                $doctop = $ipop->DR;
                #ตัดขีด,  ออก
                $pattern_drop = '/-/i';
                $dr_cutop = preg_replace($pattern_drop, '', $doctop);
                if ($dr_cutop == '') {
                    $doctop_ = 'ว47998';
                } else {
                    $doctop_ = $dr_cutop;
                }
                D_aipop::insert([
                    'an'             => $ipop->AN,
                    'sequence'       => $i++,
                    'CodeSys'        => $ipop->CodeSys,
                    'Code'           => $ipop->Code,
                    'Procterm'       => $ipop->Procterm,
                    'DR'             => $doctop_,
                    'DateIn'         => $ipop->DateIn,
                    'DateOut'        => $ipop->DateOut,
                    'Location'       => $ipop->Location
                ]);
            }

            $aipn_ipdx = DB::connection('mysql3')->select('   
                SELECT 
                    i.an as AN
                    ,"" as sequence
                    ,diagtype as DxType
                    ,if(ifnull(aa.codeset,"")="TT","ICD-10-TM","ICD-10") as CodeSys
                    ,dx.icd10 as Dcode
                    ,icdname(dx.icd10) as DiagTerm 
                    ,doctorlicense(cc.doctor) as DR  
                    ,null datediag
                    from ipt i
                    join iptdiag dx on dx.an=i.an
                    join iptoprt cc on cc.an=i.an
                    left join icd101 aa on aa.code=dx.icd10
                    WHERE i.an IN("' . $va1->an . '")  
                    group by dx.icd10
                    order by diagtype,ipt_diag_id 
            ');
            $j = 1;
            foreach ($aipn_ipdx as $key => $val_ipdx) {
                $doct = $val_ipdx->DR;
                #ตัดขีด,  ออก
                $pattern_dr = '/-/i';
                $dr_cut = preg_replace($pattern_dr, '', $doct);

                if ($dr_cut == '') {
                    $doctop_s = 'ว47998';
                } else {
                    $doctop_s = $dr_cut;
                }

                D_aipdx::insert([
                    'an'             => $val_ipdx->AN,
                    'sequence'       => $j++,
                    'DxType'         => $val_ipdx->DxType,
                    'CodeSys'        => $val_ipdx->CodeSys,
                    'Dcode'          => $val_ipdx->Dcode,
                    'DiagTerm'       => $val_ipdx->DiagTerm,
                    'DR'             => $doctop_s,
                    'datediag'       => $val_ipdx->datediag
                ]);
            }


            $update_billitems = DB::connection('mysql')->select('SELECT * FROM d_abillitems WHERE CodeSys ="TMLT" AND STDCode ="" OR ClaimCat="T" ');
            foreach ($update_billitems as $key => $valbil) {
                $id = $valbil->d_abillitems_id;
                $del = D_abillitems::find($id);
                $del->delete();
            }

            $update_billitems2 = DB::connection('mysql')->select('SELECT * FROM d_abillitems WHERE CodeSys ="TMT" AND STDCode ="" OR ClaimCat="T" ');
            foreach ($update_billitems2 as $key => $valbil2) {
                $id = $valbil2->d_abillitems_id;
                $del = D_abillitems::find($id);
                $del->delete();
            }
        }

        return response()->json([
            'status'    => '200'
        ]);
    }

    public function aipn_billitems_destroy(Request $request, $id)
    {
        $del = D_abillitems::find($id);
        $del->delete();
        return redirect()->route('claim.aipn');
        // return response()->json(['status' => '200']);
    }
    public function aipn_export(Request $request)
    {

        $aipn_date_now = date("Y-m-d");
        $aipn_time_now = date("H:i:s");

        #delete file in folder ทั้งหมด
        $file = new Filesystem;
        $file->cleanDirectory('Export'); //ทั้งหมด

        #sessionid เป็นค่าว่าง แสดงว่ายังไม่เคยส่งออก ต้องสร้างไอดีใหม่ จาก max+1
        $maxid = D_aipn_session::max('aipn_session_no');
        $aipn_session_no = $maxid + 1;

        #ตัดขีด, ตัด : ออก
        $pattern_date = '/-/i';
        $aipn_date_now_preg = preg_replace($pattern_date, '', $aipn_date_now);
        $pattern_time = '/:/i';
        $aipn_time_now_preg = preg_replace($pattern_time, '', $aipn_time_now);
        #ตัดขีด, ตัด : ออก

        $folder = '10978AIPN' . $aipn_session_no;
        $foldertxt = 'TXT' . $aipn_session_no;

        $add = new D_aipn_session();
        $add->aipn_session_no = $aipn_session_no;
        $add->aipn_session_date = $aipn_date_now;
        $add->aipn_session_time = $aipn_time_now;
        $add->aipn_session_filename = $folder;
        $add->aipn_session_ststus = "Send";
        $add->save();

        mkdir('Export/' . $folder, 0777, true);  //Web
        mkdir('Export/' . $foldertxt, 0777, true);  //Web
        //  mkdir ('C:Export/'.$folder, 0777, true); //localhost

        header("Content-type: text/txt");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="content.txt"');

        $datamain = DB::connection('mysql')->select('SELECT an FROM d_aipn_main');
        foreach ($datamain as $key => $ai) {
            $an = $ai->an;

            $file_pat = "Export/" . $foldertxt . "/10978-AIPN-" . $an . '-' . $aipn_date_now_preg . '' . $aipn_time_now_preg . ".txt";
            $objFopen_opd = fopen($file_pat, 'w');
   

            $opd_head = '<CIPN>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<Header>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<DocClass>IPClaim</DocClass>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<DocSysID version="2.1">AIPN</DocSysID>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<serviceEvent>ADT</serviceEvent>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<authorID>10978</authorID>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<authorName>รพ.ภูเขียวเฉลิมพระเกียรติ</authorName>';
            $opd_head_ansi = iconv('UTF-8', 'TIS-620', $opd_head);
            fwrite($objFopen_opd, $opd_head_ansi);

            $opd_head = "\n" . '<effectiveTime>' . $aipn_date_now . 'T' . $aipn_time_now . '</effectiveTime>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</Header>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<ClaimAuth>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<AuthCode></AuthCode>';
            fwrite($objFopen_opd, $opd_head);

            $aipn_InvNumber_ = DB::connection('mysql')->select('
                SELECT AN,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch 
                FROM d_aipadt
                WHERE AN = "'.$an.'"
            ');
            foreach ($aipn_InvNumber_ as $key => $val) {
                $inv = $val->AN;
                $audt = $val->DTAdm;
                $indt = $val->DTDisch;
            }

            $opd_head = "\n" . '<AuthDT>' . $audt . '</AuthDT>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<UPayPlan>80</UPayPlan>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ServiceType>IP</ServiceType>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ProjectCode></ProjectCode>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<EventCode> </EventCode>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<UserReserve> </UserReserve>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<Hmain>10702</Hmain>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<Hcare>10978</Hcare>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<CareAs>B</CareAs>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ServiceSubType> </ServiceSubType>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</ClaimAuth>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<IPADT>';
            fwrite($objFopen_opd, $opd_head);

            $aipn_data = DB::connection('mysql')->select('   
                    SELECT 
                    AN,HN,IDTYPE,PIDPAT,TITLE,NAMEPAT,DOB,SEX,MARRIAGE,CHANGWAT,AMPHUR,NATION,AdmType,ifnull(AdmSource,"") as AdmSource
                    ,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm
                    ,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch 
                    ,LeaveDay,DischStat,DishType,AdmWt,DishWard,Dept
                    FROM d_aipadt
                    WHERE AN = "'.$an.'" 
                ');

            foreach ($aipn_data as $key => $value2) {
                $b1 = $value2->AN;
                $b2 = $value2->HN;
                $b3 = $value2->IDTYPE;
                $b4 = $value2->PIDPAT;
                $b5 = $value2->TITLE;
                $b6 = $value2->NAMEPAT;
                $b7 = $value2->DOB;
                $b8 = $value2->SEX;
                $b9 = $value2->MARRIAGE;
                $b10 = $value2->CHANGWAT;
                $b11 = $value2->AMPHUR;
                $b12 = $value2->NATION;
                $b13 = $value2->AdmType;
                $b14 = $value2->AdmSource;
                $b15 = $value2->DTAdm;
                $b16 = $value2->DTDisch;
                $b17 = $value2->LeaveDay;
                $b18 = $value2->DischStat;
                $b19 = $value2->DishType;
                $b20 = $value2->AdmWt;
                $b21 = $value2->DishWard;
                $b22 = $value2->Dept;
                $strText2 = "\n" . $b1 . "|" . $b2 . "|" . $b3 . "|" . $b4 . "|" . $b5 . "|" . $b6 . "|" . $b7 . "|" . $b8 . "|" . $b9 . "|" . $b10 . "|" . $b11 . "|" . $b12 . "|" . $b13 . "|" . $b14 . "|" . $b15 . "|" . $b16 . "|" . $b17 . "|" . $b18 . "|" . $b19 . "|" . $b20 . "|" . $b21 . "|" . $b22;
                $ansitxt_pat2 = iconv('UTF-8', 'TIS-620', $strText2);
                fwrite($objFopen_opd, $ansitxt_pat2);
            }

            $opd_head = "\n" . '</IPADT>';
            fwrite($objFopen_opd, $opd_head);

            $ipdx_count_ = DB::connection('mysql')->select('SELECT COUNT(d_aipdx_id) as iCount FROM d_aipdx WHERE an = "'.$an.'"');
            foreach ($ipdx_count_ as $key => $value_c) {
                $ipdx_count = $value_c->iCount;
            }
            $opd_head = "\n" . '<IPDx Reccount="' . $ipdx_count . '">';
            fwrite($objFopen_opd, $opd_head);
            $ipdx = DB::connection('mysql')->select('   
                    SELECT * FROM d_aipdx WHERE an = "'.$an.'" 
                ');
            foreach ($ipdx as $key => $value_ip) {
                $s1 = $value_ip->sequence;
                $s2 = $value_ip->DxType;
                $s3 = $value_ip->CodeSys;
                $s4 = $value_ip->Dcode;
                $s5 = $value_ip->DiagTerm;
                $s6 = $value_ip->DR;

                $strText = "\n" . $s1 . "|" . $s2 . "|" . $s3 . "|" . $s4 . "|" . $s5 . "|" . $s6 . "|";
                $ansitxt_ipdx = iconv('UTF-8', 'TIS-620', $strText);
                fwrite($objFopen_opd, $ansitxt_ipdx);
            }
            $opd_head = "\n" . '</IPDx>';
            fwrite($objFopen_opd, $opd_head);

            $ipop_count_ = DB::connection('mysql')->select('SELECT COUNT(d_aipop_id) as iopcount FROM d_aipop WHERE an = "'.$an.'"');
            foreach ($ipop_count_ as $key => $value_op) {
                $ipop_count = $value_op->iopcount;
            }
            $opd_head = "\n" . '<IPOp Reccount="' . $ipop_count . '">';
            fwrite($objFopen_opd, $opd_head);

            $ipop = DB::connection('mysql')->select('   
                    SELECT 
                    sequence,CodeSys,Code,Procterm,DR,DateIn,DateOut,Location 
                    FROM d_aipop WHERE an = "'.$an.'" 
                ');
            foreach ($ipop as $key => $value_ipop) {
                $s1 = $value_ipop->sequence;
                $s2 = $value_ipop->CodeSys;
                $s3 = $value_ipop->Code;
                $s4 = $value_ipop->Procterm;
                $s5 = $value_ipop->DR;
                $s6 = $value_ipop->DateIn;
                $s7 = $value_ipop->DateOut;

                $strText = "\n" . $s1 . "|" . $s2 . "|" . $s3 . "|" . $s4 . "|" . $s5 . "|" . $s6 . "|" . $s7 . "|";
                $ansitxt_ipop = iconv('UTF-8', 'TIS-620', $strText);
                fwrite($objFopen_opd, $ansitxt_ipop);
            }
            $opd_head = "\n" . '</IPOp>';
            fwrite($objFopen_opd, $opd_head);

            $billitem_count_ = DB::connection('mysql')->select('SELECT COUNT(d_abillitems_id) as bill_count FROM d_abillitems WHERE AN = "'.$an.'"');
            foreach ($billitem_count_ as $key => $value_bill) {
                $billitem_count = $value_bill->bill_count;
            }
            $opd_head = "\n" . '<Invoices>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvNumber>' . $inv . '</InvNumber>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvDT>' . $indt . '</InvDT>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<BillItems Reccount="' . $billitem_count . '">';
            fwrite($objFopen_opd, $opd_head);

            $text_billitems_ = DB::connection('mysql')->select('SELECT * from d_abillitems WHERE AN = "'.$an.'"');
            foreach ($text_billitems_ as $key => $bitem) {
                $t1 = $bitem->sequence;
                $t2 = $bitem->ServDate;
                $t3 = $bitem->BillGr;
                $t4 = $bitem->LCCode;
                $t5 = $bitem->Descript;
                $t6 = $bitem->QTY;
                $t7 = $bitem->UnitPrice;
                $t8 = $bitem->ChargeAmt;
                $t9 = $bitem->Discount;
                $t10 = $bitem->ProcedureSeq;
                $t11 = $bitem->DiagnosisSeq;
                $t12 = $bitem->ClaimSys;
                $t13 = $bitem->BillGrCS;
                $t14 = $bitem->CSCode;
                $t15 = $bitem->CodeSys;
                $t16 = $bitem->STDCode;
                $t17 = $bitem->ClaimCat;
                $t18 = $bitem->DateRev;
                $t19 = $bitem->ClaimUP;
                $t20 = $bitem->ClaimAmt;
 
                $strTextbill = "\n" . $t1 . "|" . $t2 . "|" . $t3 . "|" . $t4 . "|" . $t5 . "|" . $t6 . "|" . $t7 . "|" . $t8 . "|" . $t9 . "|" . $t10 . "|" . $t11 . "|" . $t12 . "|" . $t13 . "|" . $t14 . "|" . $t15 . "|" . $t16 . "|" . $t17 . "|" . $t18 . "|" . $t19 . "|" . $t20;
                $ansitxt_bitemss = iconv('UTF-8', 'TIS-620', $strTextbill);
                fwrite($objFopen_opd, $ansitxt_bitemss);
            }
            $sum_billitems_ = DB::connection('mysql')->select('SELECT SUM(ChargeAmt) as Total from d_abillitems WHERE AN = "'.$an.'"');
            foreach ($sum_billitems_ as $key => $value_sum) {
                $sum_billitems = $value_sum->Total;
            }

            $opd_head = "\n" . '</BillItems>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvAddDiscount>0.00</InvAddDiscount>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<DRGCharge>' . $sum_billitems . '</DRGCharge>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<XDRGClaim>0.0000</XDRGClaim>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</Invoices>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<Coinsurance> </Coinsurance>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</CIPN>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n";
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n";
            fwrite($objFopen_opd, $opd_head);


            $md5file = md5_file($file_pat, FALSE);
            $mdup = strtoupper($md5file);
 
            // ********************HASH MD5********************
 
            // ********************File 2  ********************
            $file_pat2 = "Export/" . $folder . "/10978-AIPN-" .$an.'-'. $aipn_date_now_preg . '' . $aipn_time_now_preg . ".xml";
            $objFopen_opd2 = fopen($file_pat2, 'w'); 

     
            $opd_head2 = '<?xml version="1.0" encoding="windows-874"?>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<CIPN>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<Header>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<DocClass>IPClaim</DocClass>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<DocSysID version="2.1">AIPN</DocSysID>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<serviceEvent>ADT</serviceEvent>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<authorID>10978</authorID>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<authorName>รพ.ภูเขียวเฉลิมพระเกียรติ</authorName>';
            $opd_head_ansi2 = iconv('UTF-8', 'TIS-620', $opd_head2);
            fwrite($objFopen_opd2, $opd_head_ansi2);

            $opd_head2 = "\n" . '<effectiveTime>' . $aipn_date_now . 'T' . $aipn_time_now . '</effectiveTime>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</Header>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<ClaimAuth>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<AuthCode></AuthCode>';
            fwrite($objFopen_opd2, $opd_head2);

            $aipn_InvNumber_2 = DB::connection('mysql')->select('SELECT AN,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch FROM d_aipadt WHERE AN = "'.$an.'"');
            foreach ($aipn_InvNumber_2 as $key => $val2) {
                $inv2 = $val2->AN;
                $audt2 = $val2->DTAdm;
                $indt2 = $val2->DTDisch;
            }

            $opd_head2 = "\n" . '<AuthDT>' . $audt2 . '</AuthDT>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<UPayPlan>80</UPayPlan>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ServiceType>IP</ServiceType>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ProjectCode></ProjectCode>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<EventCode> </EventCode>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<UserReserve> </UserReserve>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<Hmain>10702</Hmain>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<Hcare>10978</Hcare>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<CareAs>B</CareAs>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ServiceSubType> </ServiceSubType>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</ClaimAuth>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<IPADT>';
            fwrite($objFopen_opd2, $opd_head2);

            $aipn_data2 = DB::connection('mysql')->select('   
                    SELECT 
                    AN,HN,IDTYPE,PIDPAT,TITLE,NAMEPAT,DOB,SEX,MARRIAGE,CHANGWAT,AMPHUR,NATION,AdmType,AdmSource 
                    ,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm
                    ,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch 
                    ,LeaveDay,DischStat,DishType,AdmWt,DishWard,Dept
                    FROM d_aipadt 
                    WHERE AN = "'.$an.'"
                ');

            foreach ($aipn_data2 as $key => $value22) {
                $bb1 = $value22->AN;
                $bb2 = $value22->HN;
                $bb3 = $value22->IDTYPE;
                $bb4 = $value22->PIDPAT;
                $bb5 = $value22->TITLE;
                $bb6 = $value22->NAMEPAT;
                $bb7 = $value22->DOB;
                $bb8 = $value22->SEX;
                $bb9 = $value22->MARRIAGE;
                $bb10 = $value22->CHANGWAT;
                $bb11 = $value22->AMPHUR;
                $bb12 = $value22->NATION;
                $bb13 = $value22->AdmType;
                $bb14 = $value22->AdmSource;
                $bb15 = $value22->DTAdm;
                $bb16 = $value22->DTDisch;
                $bb17 = $value22->LeaveDay;
                $bb18 = $value22->DischStat;
                $bb19 = $value22->DishType;
                $bb20 = $value22->AdmWt;
                $bb21 = $value22->DishWard;
                $bb22 = $value22->Dept;
                $strText22 = "\n" . $bb1 . "|" . $bb2 . "|" . $bb3 . "|" . $bb4 . "|" . $bb5 . "|" . $bb6 . "|" . $bb7 . "|" . $bb8 . "|" . $bb9 . "|" . $bb10 . "|" . $bb11 . "|" . $bb12 . "|" . $bb13 . "|" . $bb14 . "|" . $bb15 . "|" . $bb16 . "|" . $bb17 . "|" . $bb18 . "|" . $bb19 . "|" . $bb20 . "|" . $bb21 . "|" . $bb22;
                $ansitxt_pat22 = iconv('UTF-8', 'TIS-620', $strText22);
                fwrite($objFopen_opd2, $ansitxt_pat22);
            }

            $opd_head2 = "\n" . '</IPADT>';
            fwrite($objFopen_opd2, $opd_head2);

            $ipdx_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_aipdx_id) as iCount FROM d_aipdx WHERE an = "'.$an.'"');
            foreach ($ipdx_count_2 as $key => $value_c2) {
                $ipdx_count2 = $value_c2->iCount;
            }
            $opd_head2 = "\n" . '<IPDx Reccount="' . $ipdx_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $ipdx2 = DB::connection('mysql')->select('   
                    SELECT * FROM d_aipdx WHERE an = "'.$an.'" 
                ');
            foreach ($ipdx2 as $key => $value_ip2) {
                $ss1 = $value_ip2->sequence;
                $ss2 = $value_ip2->DxType;
                $ss3 = $value_ip2->CodeSys;
                $ss4 = $value_ip2->Dcode;
                $ss5 = $value_ip2->DiagTerm;
                $ss6 = $value_ip2->DR;

                $strTexts = "\n" . $ss1 . "|" . $ss2 . "|" . $ss3 . "|" . $ss4 . "|" . $ss5 . "|" . $ss6 . "|";
                $ansitxt_ipdxs = iconv('UTF-8', 'TIS-620', $strTexts);
                fwrite($objFopen_opd2, $ansitxt_ipdxs);
            }
            $opd_head2 = "\n" . '</IPDx>';
            fwrite($objFopen_opd2, $opd_head2);

            $ipop_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_aipop_id) as iopcount FROM d_aipop WHERE an = "'.$an.'"');
            foreach ($ipop_count_2 as $key => $value_op2) {
                $ipop_count2 = $value_op2->iopcount;
            }
            $opd_head2 = "\n" . '<IPOp Reccount="' . $ipop_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $ipop2 = DB::connection('mysql')->select('   
                    SELECT 
                    sequence,CodeSys,Code,Procterm,DR,DateIn,DateOut,Location 
                    FROM d_aipop WHERE an = "'.$an.'" 
                ');
            foreach ($ipop2 as $key => $value_ipop2) {
                $so1 = $value_ipop2->sequence;
                $so2 = $value_ipop2->CodeSys;
                $so3 = $value_ipop2->Code;
                $so4 = $value_ipop2->Procterm;
                $so5 = $value_ipop2->DR;
                $so6 = $value_ipop2->DateIn;
                $so7 = $value_ipop2->DateOut;

                $strTexto = "\n" . $so1 . "|" . $so2 . "|" . $so3 . "|" . $so4 . "|" . $so5 . "|" . $so6 . "|" . $so7 . "|";
                $ansitxt_ipopoo = iconv('UTF-8', 'TIS-620', $strTexto);
                fwrite($objFopen_opd2, $ansitxt_ipopoo);
            }
            $opd_head2 = "\n" . '</IPOp>';
            fwrite($objFopen_opd2, $opd_head2);

            $billitem_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_abillitems_id) as bill_count FROM d_abillitems WHERE AN = "'.$an.'"');
            foreach ($billitem_count_2 as $key => $value_bill2) {
                $billitem_count2 = $value_bill2->bill_count;
            }
            $opd_head2 = "\n" . '<Invoices>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvNumber>' . $inv2 . '</InvNumber>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvDT>' . $indt2 . '</InvDT>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<BillItems Reccount="' . $billitem_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $text_billitems_2 = DB::connection('mysql')->select('SELECT * from d_abillitems WHERE AN = "'.$an.'"');
            foreach ($text_billitems_2 as $key => $bitem2) {
                $at1 = $bitem2->sequence;
                $at2 = $bitem2->ServDate;
                $at3 = $bitem2->BillGr;
                $at4 = $bitem2->LCCode;
                $at5 = $bitem2->Descript;
                $at6 = $bitem2->QTY;
                $at7 = $bitem2->UnitPrice;
                $at8 = $bitem2->ChargeAmt;
                $at9 = $bitem2->Discount;
                $at10 = $bitem2->ProcedureSeq;
                $at11 = $bitem2->DiagnosisSeq;
                $at12 = $bitem2->ClaimSys;
                $at13 = $bitem2->BillGrCS;
                $at14 = $bitem2->CSCode;
                $at15 = $bitem2->CodeSys;
                $at16 = $bitem2->STDCode;
                $at17 = $bitem2->ClaimCat;
                $at18 = $bitem2->DateRev;
                $at19 = $bitem2->ClaimUP;
                $at20 = $bitem2->ClaimAmt;

                $strTextD22 = "\n" . $at1 . "|" . $at2 . "|" . $at3 . "|" . $at4 . "|" . $at5 . "|" . $at6 . "|" . $at7 . "|" . $at8 . "|" . $at9 . "|" . $at10 . "|" . $at11 . "|" . $at12 . "|" . $at13 . "|" . $at14 . "|" . $at15 . "|" . $at16 . "|" . $at17 . "|" . $at18 . "|" . $at19 . "|" . $at20;
                $ansitxt_bitem2 = iconv('UTF-8', 'TIS-620', $strTextD22);
                fwrite($objFopen_opd2, $ansitxt_bitem2);
            }
            $sum_billitems_a2 = DB::connection('mysql')->select('SELECT SUM(ChargeAmt) as Total from d_abillitems WHERE AN = "'.$an.'"');
            foreach ($sum_billitems_a2 as $key => $value_sum2) {
                $sum_billitemsa2 = $value_sum2->Total;
            }

            $opd_head2 = "\n" . '</BillItems>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvAddDiscount>0.00</InvAddDiscount>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<DRGCharge>' . $sum_billitemsa2 . '</DRGCharge>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<XDRGClaim>0.0000</XDRGClaim>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</Invoices>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<Coinsurance> </Coinsurance>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</CIPN>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            $objFopen_opd2 = fopen($file_pat2, 'a');
            $opd_head2 = '<?EndNote HMAC="' . $mdup . '" ?>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            fclose($objFopen_opd2);
        }
 
        // }

        // return redirect()->route('data.six');
        // return redirect()->back();
        return response()->json([
            'status'    => '200'
        ]);
    }

    
    public function aipn_zip(Request $request)
    {
        $filename = D_aipn_session::max('aipn_session_no');
        $nzip = D_aipn_session::where('aipn_session_no', '=', $filename)->first();
        $namezip = $nzip->aipn_session_filename;
        $pathdir = "Export/" . $namezip . "/";
        $zipcreated = $namezip . ".zip";

        $newzip = new ZipArchive;
        if ($newzip->open($zipcreated, ZipArchive::CREATE) === TRUE) {
            $dir = opendir($pathdir);
            while ($file = readdir($dir)) {
                if (is_file($pathdir . $file)) {
                    $newzip->addFile($pathdir . $file, $file);
                }
            }
            // dd($newzip);
            $newzip->close();
            if (file_exists($zipcreated)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zipcreated) . '"');
                header('Content-Length: ' . filesize($zipcreated));
                flush();
                readfile($zipcreated);
                unlink($zipcreated);
                $files = glob($pathdir . '/*');

                foreach ($files as $file) {
                    if (is_file($file)) {
                    }
                }

                return redirect()->back();
                // return response()->json([
                //     'status'    => '200'
                // ]);                 
            }
        }
        //   return response()->json([
        //             'status'    => '200'
        //         ]);  

    }

    public function aipn_main_an(Request $request)
    {
        D_aipn_main::truncate();
        D_aipadt::truncate();
        D_aipdx::truncate();
        D_aipop::truncate();
        D_abillitems::truncate();

        $an = $request->AN; 
        $date = date('Y-m-d');
        $iduser = Auth::user()->id;
       
        $data_main_ = DB::connection('mysql2')->select(' 
                SELECT
                i.an,i.vn,a.hn,p.cid,i.regdate,i.regtime,i.dchdate,i.dchtime,i.pttype,concat(p.pname,p.fname," ",p.lname) ptname
                ,pt.hipdata_code,a.income,a.income-a.rcpt_money-a.discount_money as debit
                FROM ipt i
                LEFT OUTER JOIN patient p on p.hn=i.hn 
                LEFT OUTER JOIN an_stat a on a.an=i.an 
                LEFT OUTER JOIN pttype pt on pt.pttype=i.pttype 
                LEFT OUTER JOIN opitemrece op on op.an=i.an 
                LEFT OUTER JOIN s_drugitems d on d.icode=op.icode 
                WHERE i.an = "' . $an . '"   
                group by i.an 
        ');
       
        foreach ($data_main_ as $key => $value) {
            D_aipn_main::insert([
                'vn'                => $value->vn,
                'hn'                => $value->hn,
                'an'                => $value->an,
                'dchdate'           => $value->dchdate,
                'debit'             => $value->debit
            ]);
            $check = D_claim::where('an', $value->an)->where('nhso_adp_code', 'AIPN')->count();
            if ($check > 0) {
                # code...
            } else {
                D_claim::insert([
                    'vn'                => $value->vn,
                    'hn'                => $value->hn,
                    'an'                => $value->an,
                    'cid'               => $value->cid,
                    'pttype'            => $value->pttype,
                    'ptname'            => $value->ptname,
                    'dchdate'           => $value->dchdate,
                    'hipdata_code'      => $value->hipdata_code,
                    // 'qty'               => $value->qty,
                    'sum_price'         => $value->debit,
                    'type'              => 'IPD',
                    'nhso_adp_code'     => 'AIPN',
                    'claimdate'         => $date,
                    'userid'            => $iduser,
                ]);
            }
        }

        return response()->json([
            'status'    => '200'
        ]);
    }

    public function aipn_process_an(Request $request)
    {
        

        $data_aipn = DB::connection('mysql')->select('SELECT vn,an from d_aipn_main');
        $va1 = D_aipn_main::where('d_aipn_main_id','=','1')->first();
        $iduser = Auth::user()->id;
         
            $aipn_data = DB::connection('mysql2')->select('   
                    SELECT
                    i.an,  
                    i.an as AN,i.hn as HN,"0" as IDTYPE 
                    ,pt.cid as PIDPAT
                    ,pt.pname as TITLE
                    ,concat(pt.fname," ",pt.lname) as NAMEPAT 
                    ,pt.birthday as DOB
                    ,a.sex as SEX
                    ,pt.marrystatus as MARRIAGE
                    ,pt.chwpart as CHANGWAT
                    ,pt.amppart as AMPHUR
                    ,pt.citizenship as NATION
                    ,"C" as AdmType
                    ,"O" as AdmSource
                    ,i.regdate as DTAdm_d
                    ,i.regtime as DTAdm_t
                    ,i.dchdate as DTDisch_d
                    ,i.dchtime as DTDisch_t 
                    ,"0" AS LeaveDay                
                    ,i.dchstts as DischStat
                    ,i.dchtype as DishType
                    ,"" as AdmWt
                    ,i.ward as DishWard
                    ,sp.nhso_code as Dept
                    ,ptt.hipdata_code maininscl
                    ,i.pttype
                    ,concat(i.pttype,":",ptt.name) pttypename 
                    ,"10702" HMAIN
                    ,"IP" as ServiceType
                    from ipt i
                    LEFT OUTER JOIN patient pt on pt.hn=i.hn
                    LEFT OUTER JOIN ptcardno pc on pc.hn=pt.hn and pc.cardtype="02"
                    LEFT OUTER JOIN an_stat a on a.an=i.an
                    LEFT OUTER JOIN spclty sp on sp.spclty=i.spclty
                    LEFT OUTER JOIN pttype ptt on ptt.pttype=i.pttype
                    LEFT OUTER JOIN pttype_eclaim ec on ec.code=ptt.pttype_eclaim_id 
                    LEFT OUTER JOIN opitemrece oo on oo.an=i.an
                    LEFT OUTER JOIN income inc on inc.income=oo.income
                    LEFT OUTER JOIN s_drugitems d on d.icode=oo.icode 
                    WHERE i.an = "' . $va1->an . '"                   
                    AND ptt.pttype IN("A7","s7","14")
                    group by i.an 
    
            ');
            foreach ($aipn_data as $key => $value) {
                D_aipadt::insert([
                    'AN'             => $value->AN,
                    'HN'             => $value->HN,
                    'IDTYPE'         => $value->IDTYPE,
                    'PIDPAT'         => $value->PIDPAT,
                    'TITLE'          => $value->TITLE,
                    'NAMEPAT'        => $value->NAMEPAT,
                    'DOB'            => $value->DOB,
                    'SEX'            => $value->SEX,
                    'MARRIAGE'       => $value->MARRIAGE,
                    'CHANGWAT'       => $value->CHANGWAT,
                    'AMPHUR'         => $value->AMPHUR,
                    'NATION'         => $value->NATION,
                    'AdmType'        => $value->AdmType,
                    'AdmSource'      => $value->AdmSource,
                    'DTAdm_d'        => $value->DTAdm_d,
                    'DTAdm_t'        => $value->DTAdm_t,
                    'DTDisch_d'      => $value->DTDisch_d,
                    'DTDisch_t'      => $value->DTDisch_t,
                    'LeaveDay'       => $value->LeaveDay,
                    'DischStat'      => $value->DischStat,
                    'DishType'       => $value->DishType,
                    'AdmWt'          => $value->AdmWt,
                    'DishWard'       => $value->DishWard,
                    'Dept'           => $value->Dept,
                    'HMAIN'          => $value->HMAIN,
                    'ServiceType'    => $value->ServiceType
                ]);
            }

            //D_abillitems
            $aipn_billitems = DB::connection('mysql3')->select('   
                    SELECT  i.an,
                    i.an as AN,"" as sequence                            
                    ,i.regdate as DTAdm_d
                    ,i.regtime as DTAdm_t
                    ,i.dchdate as ServDate
                    ,i.dchtime as ServTime 
                    ,case 
                    when oo.item_type="H" then "04"
                    else zero(inc.income) end BillGr 
                    
                    ,inc.income as BillGrCS 
                                                    
                    ,ifnull(case  
                    when inc.income in (02) then d.nhso_adp_code
                    when inc.income in (03,04) then dd.billcode
                    when inc.income in (06,07) then d.nhso_adp_code
                    else d.nhso_adp_code end,"") CSCode

                    ,ifnull(case  
                    when inc.income in (03,04) then dd.tmt_tmlt
                    when inc.income in (06,07) then dd.tmt_tmlt
                    else "" end,"") STDCode

                    ,ifnull(case                 
                    when inc.income in (03,04) then "TMT"
                    when inc.income in (06,07) then "TMLT"
                    else "" end,"") CodeSys

                    ,oo.icode as LCCode
                    ,concat_ws("",d.name,d.strength) Descript
                    ,sum(oo.qty) as QTY
                    ,oo.UnitPrice as pricehos
                    ,dd.UnitPrice as pricecat
                    ,sum(oo.sum_price) ChargeAmt_ 
                    ,dd.tmt_tmlt 
                    ,inc.income

                    ,case 
                    when oo.paidst in ("01","03") then "T"
                    else "D" end ClaimCat

                    ,"0" as ClaimUP
                    ,"0" as ClaimAmt
                    ,i.dchdate
                    ,i.dchtime
                    ,sum(if(oo.paidst="04",sum_price,0)) Discount    
                    from ipt i
                    left outer join opitemrece oo on oo.an=i.an
                    left outer join an_stat a on a.an=i.an
                    left outer join patient pt on i.hn=pt.hn
                    left outer join income inc on inc.income=oo.income
                
                    left outer join s_drugitems d on oo.icode=d.icode
                    left join claim.aipn_drugcat_labcat dd on dd.icode=oo.icode	
                    left join claim.aipn_labcat_sks ls on ls.lccode=oo.icode
                    left join claim.aipn_drugcat_sks dks on dks.hospdcode=oo.icode

                    WHERE i.an = "' . $va1->an . '"                        
                    and oo.qty<>0
                    and oo.UnitPrice<>0  
                    and inc.income NOT IN ("02","22" )      
                    group by oo.icode
                    order by i.an desc
            ');
            $i = 1;
            foreach ($aipn_billitems as $key => $val_bill) {
                // $codesys = $val_bill->BillGr;
                $cs_ = $val_bill->BillGrCS;
                $cs = $val_bill->CSCode;
                // $billcs = $val_bill->BillGrCS; 

                if ($cs_ == '03') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '02') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '06') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '04') {
                    $csys = $val_bill->CodeSys;
                } elseif ($cs_ == '07') {
                    $csys = $val_bill->CodeSys;
                } else {
                    $csys = '';
                }

                if ($cs == 'XXXX') {
                    $cs_code = '';
                } elseif ($cs == 'XXXXX') {
                    $cs_code = '';
                } elseif ($cs == 'XXXXXX') {
                    $cs_code = '';
                    // }elseif ($cs == '04') {
                    //     $cs_ = '';
                } else {
                    $cs_code = $val_bill->CSCode;
                }

                D_abillitems::insert([
                    'AN'                => $val_bill->AN,
                    'sequence'          => $i++,
                    'ServDate'          => $val_bill->ServDate,
                    'ServTime'          => $val_bill->ServTime,
                    'BillGr'            => $val_bill->BillGr,
                    'BillGrCS'          => $cs_,
                    'CSCode'            => $cs_code,
                    'LCCode'            => $val_bill->LCCode,
                    'Descript'          => $val_bill->Descript,
                    'QTY'               => $val_bill->QTY,
                    'UnitPrice'         => $val_bill->pricehos,
                    'ChargeAmt'         => $val_bill->QTY * $val_bill->pricehos,
                    'ClaimSys'          => "SS",
                    'CodeSys'           => $csys,
                    'STDCode'           => $val_bill->STDCode,
                    'Discount'          => "0.0000",
                    'ProcedureSeq'      => "0",
                    'DiagnosisSeq'      => "0",
                    'DateRev'           => $val_bill->ServDate,
                    'ClaimCat'          => $val_bill->ClaimCat,
                    'ClaimUP'           => $val_bill->ClaimUP,
                    'ClaimAmt'          => $val_bill->ClaimAmt
                ]);
            }

            //D_aipop
            $aipn_ipop = DB::connection('mysql3')->select('   
                SELECT
                    i.an as AN,"" as sequence,"ICD9CM" as CodeSys 
                    ,cc.icd9 as Code,icdname(cc.icd9) as Procterm,doctorlicense(cc.doctor) as DR                        
                    ,date_format(if(opdate is null,caldatetime(regdate,regtime),caldatetime(opdate,optime)),"%Y-%m-%dT%T") as DateIn
                    ,date_format(if(enddate is null,caldatetime(regdate,regtime),caldatetime(enddate,endtime)),"%Y-%m-%dT%T") as DateOut
                    ," " as Location
                    from ipt i
                    join iptoprt cc on cc.an=i.an
                    WHERE i.an = "' . $va1->an . '"  
                    group by cc.icd9
            ');
            $i = 1;
            foreach ($aipn_ipop as $key => $ipop) {
                $doctop = $ipop->DR;
                #ตัดขีด,  ออก
                $pattern_drop = '/-/i';
                $dr_cutop = preg_replace($pattern_drop, '', $doctop);
                if ($dr_cutop == '') {
                    $doctop_ = 'ว47998';
                } else {
                    $doctop_ = $dr_cutop;
                }
                D_aipop::insert([
                    'an'             => $ipop->AN,
                    'sequence'       => $i++,
                    'CodeSys'        => $ipop->CodeSys,
                    'Code'           => $ipop->Code,
                    'Procterm'       => $ipop->Procterm,
                    'DR'             => $doctop_,
                    'DateIn'         => $ipop->DateIn,
                    'DateOut'        => $ipop->DateOut,
                    'Location'       => $ipop->Location
                ]);
            }

            $aipn_ipdx = DB::connection('mysql3')->select('   
                SELECT 
                    i.an as AN
                    ,"" as sequence
                    ,diagtype as DxType
                    ,if(ifnull(aa.codeset,"")="TT","ICD-10-TM","ICD-10") as CodeSys
                    ,dx.icd10 as Dcode
                    ,icdname(dx.icd10) as DiagTerm 
                    ,doctorlicense(cc.doctor) as DR  
                    ,null datediag
                    from ipt i
                    join iptdiag dx on dx.an=i.an
                    join iptoprt cc on cc.an=i.an
                    left join icd101 aa on aa.code=dx.icd10
                    WHERE i.an = "' . $va1->an . '" 
                    group by dx.icd10
                    order by diagtype,ipt_diag_id 
            ');
            $j = 1;
            foreach ($aipn_ipdx as $key => $val_ipdx) {
                $doct = $val_ipdx->DR;
                #ตัดขีด,  ออก
                $pattern_dr = '/-/i';
                $dr_cut = preg_replace($pattern_dr, '', $doct);

                if ($dr_cut == '') {
                    $doctop_s = 'ว47998';
                } else {
                    $doctop_s = $dr_cut;
                }

                D_aipdx::insert([
                    'an'             => $val_ipdx->AN,
                    'sequence'       => $j++,
                    'DxType'         => $val_ipdx->DxType,
                    'CodeSys'        => $val_ipdx->CodeSys,
                    'Dcode'          => $val_ipdx->Dcode,
                    'DiagTerm'       => $val_ipdx->DiagTerm,
                    'DR'             => $doctop_s,
                    'datediag'       => $val_ipdx->datediag
                ]);
            }


            $update_billitems = DB::connection('mysql')->select('SELECT * FROM d_abillitems WHERE CodeSys ="TMLT" AND STDCode ="" OR ClaimCat="T" ');
            foreach ($update_billitems as $key => $valbil) {
                $id = $valbil->d_abillitems_id;
                $del = D_abillitems::find($id);
                $del->delete();
            }

            $update_billitems2 = DB::connection('mysql')->select('SELECT * FROM d_abillitems WHERE CodeSys ="TMT" AND STDCode ="" OR ClaimCat="T" ');
            foreach ($update_billitems2 as $key => $valbil2) {
                $id = $valbil2->d_abillitems_id;
                $del = D_abillitems::find($id);
                $del->delete();
            }
      
        return response()->json([
            'status'    => '200'
        ]);
    }

    public function aipn_export_an(Request $request)
    {
        $va1 = D_aipn_main::where('d_aipn_main_id','=','1')->first();
        $an = $va1->an;

        $aipn_date_now = date("Y-m-d");
        $aipn_time_now = date("H:i:s");

        #delete file in folder ทั้งหมด
        $file = new Filesystem;
        $file->cleanDirectory('Export'); //ทั้งหมด

        #sessionid เป็นค่าว่าง แสดงว่ายังไม่เคยส่งออก ต้องสร้างไอดีใหม่ จาก max+1
        $maxid = D_aipn_session::max('aipn_session_no');
        $aipn_session_no = $maxid + 1;

        #ตัดขีด, ตัด : ออก
        $pattern_date = '/-/i';
        $aipn_date_now_preg = preg_replace($pattern_date, '', $aipn_date_now);
        $pattern_time = '/:/i';
        $aipn_time_now_preg = preg_replace($pattern_time, '', $aipn_time_now);
        #ตัดขีด, ตัด : ออก

        $folder = '10978AIPN' . $aipn_session_no;
        $foldertxt = 'TXT' . $aipn_session_no;

        $add = new D_aipn_session();
        $add->aipn_session_no = $aipn_session_no;
        $add->aipn_session_date = $aipn_date_now;
        $add->aipn_session_time = $aipn_time_now;
        $add->aipn_session_filename = $folder;
        $add->aipn_session_ststus = "Send";
        $add->save();

        mkdir('Export/' . $folder, 0777, true);  //Web
        mkdir('Export/' . $foldertxt, 0777, true);  //Web
        //  mkdir ('C:Export/'.$folder, 0777, true); //localhost

        header("Content-type: text/txt");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="content.txt"');

        $datamain = DB::connection('mysql')->select('SELECT an FROM d_aipn_main');
         
            $file_pat = "Export/" . $foldertxt . "/10978-AIPN-" . $an . '-' . $aipn_date_now_preg . '' . $aipn_time_now_preg . ".txt";
            $objFopen_opd = fopen($file_pat, 'w');
            // dd($file_pat);

            $opd_head = '<CIPN>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<Header>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<DocClass>IPClaim</DocClass>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<DocSysID version="2.1">AIPN</DocSysID>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<serviceEvent>ADT</serviceEvent>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<authorID>10978</authorID>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<authorName>รพ.ภูเขียวเฉลิมพระเกียรติ</authorName>';
            $opd_head_ansi = iconv('UTF-8', 'TIS-620', $opd_head);
            fwrite($objFopen_opd, $opd_head_ansi);

            $opd_head = "\n" . '<effectiveTime>' . $aipn_date_now . 'T' . $aipn_time_now . '</effectiveTime>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</Header>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<ClaimAuth>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<AuthCode></AuthCode>';
            fwrite($objFopen_opd, $opd_head);

            $aipn_InvNumber_ = DB::connection('mysql')->select('SELECT AN,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch FROM d_aipadt');
            foreach ($aipn_InvNumber_ as $key => $val) {
                $inv = $val->AN;
                $audt = $val->DTAdm;
                $indt = $val->DTDisch;
            }

            $opd_head = "\n" . '<AuthDT>' . $audt . '</AuthDT>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<UPayPlan>80</UPayPlan>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ServiceType>IP</ServiceType>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ProjectCode></ProjectCode>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<EventCode> </EventCode>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<UserReserve> </UserReserve>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<Hmain>10702</Hmain>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<Hcare>10978</Hcare>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<CareAs>B</CareAs>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<ServiceSubType> </ServiceSubType>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</ClaimAuth>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<IPADT>';
            fwrite($objFopen_opd, $opd_head);

            $aipn_data = DB::connection('mysql')->select('   
                    SELECT 
                    AN,HN,IDTYPE,PIDPAT,TITLE,NAMEPAT,DOB,SEX,MARRIAGE,CHANGWAT,AMPHUR,NATION,AdmType,ifnull(AdmSource,"") as AdmSource
                    ,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm
                    ,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch 
                    ,LeaveDay,DischStat,DishType,AdmWt,DishWard,Dept
                    FROM d_aipadt 
                ');

            foreach ($aipn_data as $key => $value2) {
                $b1 = $value2->AN;
                $b2 = $value2->HN;
                $b3 = $value2->IDTYPE;
                $b4 = $value2->PIDPAT;
                $b5 = $value2->TITLE;
                $b6 = $value2->NAMEPAT;
                $b7 = $value2->DOB;
                $b8 = $value2->SEX;
                $b9 = $value2->MARRIAGE;
                $b10 = $value2->CHANGWAT;
                $b11 = $value2->AMPHUR;
                $b12 = $value2->NATION;
                $b13 = $value2->AdmType;
                $b14 = $value2->AdmSource;
                $b15 = $value2->DTAdm;
                $b16 = $value2->DTDisch;
                $b17 = $value2->LeaveDay;
                $b18 = $value2->DischStat;
                $b19 = $value2->DishType;
                $b20 = $value2->AdmWt;
                $b21 = $value2->DishWard;
                $b22 = $value2->Dept;
                $strText2 = "\n" . $b1 . "|" . $b2 . "|" . $b3 . "|" . $b4 . "|" . $b5 . "|" . $b6 . "|" . $b7 . "|" . $b8 . "|" . $b9 . "|" . $b10 . "|" . $b11 . "|" . $b12 . "|" . $b13 . "|" . $b14 . "|" . $b15 . "|" . $b16 . "|" . $b17 . "|" . $b18 . "|" . $b19 . "|" . $b20 . "|" . $b21 . "|" . $b22;
                $ansitxt_pat2 = iconv('UTF-8', 'TIS-620', $strText2);
                fwrite($objFopen_opd, $ansitxt_pat2);
            }

            $opd_head = "\n" . '</IPADT>';
            fwrite($objFopen_opd, $opd_head);

            $ipdx_count_ = DB::connection('mysql')->select('SELECT COUNT(d_aipdx_id) as iCount FROM d_aipdx');
            foreach ($ipdx_count_ as $key => $value_c) {
                $ipdx_count = $value_c->iCount;
            }
            $opd_head = "\n" . '<IPDx Reccount="' . $ipdx_count . '">';
            fwrite($objFopen_opd, $opd_head);
            $ipdx = DB::connection('mysql')->select('   
                    SELECT * FROM d_aipdx  
                ');
            foreach ($ipdx as $key => $value_ip) {
                $s1 = $value_ip->sequence;
                $s2 = $value_ip->DxType;
                $s3 = $value_ip->CodeSys;
                $s4 = $value_ip->Dcode;
                $s5 = $value_ip->DiagTerm;
                $s6 = $value_ip->DR;

                $strText = "\n" . $s1 . "|" . $s2 . "|" . $s3 . "|" . $s4 . "|" . $s5 . "|" . $s6 . "|";
                $ansitxt_ipdx = iconv('UTF-8', 'TIS-620', $strText);
                fwrite($objFopen_opd, $ansitxt_ipdx);
            }
            $opd_head = "\n" . '</IPDx>';
            fwrite($objFopen_opd, $opd_head);

            $ipop_count_ = DB::connection('mysql')->select('SELECT COUNT(d_aipop_id) as iopcount FROM d_aipop');
            foreach ($ipop_count_ as $key => $value_op) {
                $ipop_count = $value_op->iopcount;
            }
            $opd_head = "\n" . '<IPOp Reccount="' . $ipop_count . '">';
            fwrite($objFopen_opd, $opd_head);

            $ipop = DB::connection('mysql')->select('   
                    SELECT 
                    sequence,CodeSys,Code,Procterm,DR,DateIn,DateOut,Location 
                    FROM d_aipop  
                ');
            foreach ($ipop as $key => $value_ipop) {
                $s1 = $value_ipop->sequence;
                $s2 = $value_ipop->CodeSys;
                $s3 = $value_ipop->Code;
                $s4 = $value_ipop->Procterm;
                $s5 = $value_ipop->DR;
                $s6 = $value_ipop->DateIn;
                $s7 = $value_ipop->DateOut;

                $strText = "\n" . $s1 . "|" . $s2 . "|" . $s3 . "|" . $s4 . "|" . $s5 . "|" . $s6 . "|" . $s7 . "|";
                $ansitxt_ipop = iconv('UTF-8', 'TIS-620', $strText);
                fwrite($objFopen_opd, $ansitxt_ipop);
            }
            $opd_head = "\n" . '</IPOp>';
            fwrite($objFopen_opd, $opd_head);

            $billitem_count_ = DB::connection('mysql')->select('SELECT COUNT(d_abillitems_id) as bill_count FROM d_abillitems');
            foreach ($billitem_count_ as $key => $value_bill) {
                $billitem_count = $value_bill->bill_count;
            }
            $opd_head = "\n" . '<Invoices>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvNumber>' . $inv . '</InvNumber>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvDT>' . $indt . '</InvDT>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<BillItems Reccount="' . $billitem_count . '">';
            fwrite($objFopen_opd, $opd_head);

            $text_billitems_ = DB::connection('mysql')->select('SELECT * from d_abillitems');
            foreach ($text_billitems_ as $key => $bitem) {
                $t1 = $bitem->sequence;
                $t2 = $bitem->ServDate;
                $t3 = $bitem->BillGr;
                $t4 = $bitem->LCCode;
                $t5 = $bitem->Descript;
                $t6 = $bitem->QTY;
                $t7 = $bitem->UnitPrice;
                $t8 = $bitem->ChargeAmt;
                $t9 = $bitem->Discount;
                $t10 = $bitem->ProcedureSeq;
                $t11 = $bitem->DiagnosisSeq;
                $t12 = $bitem->ClaimSys;
                $t13 = $bitem->BillGrCS;
                $t14 = $bitem->CSCode;
                $t15 = $bitem->CodeSys;
                $t16 = $bitem->STDCode;
                $t17 = $bitem->ClaimCat;
                $t18 = $bitem->DateRev;
                $t19 = $bitem->ClaimUP;
                $t20 = $bitem->ClaimAmt;
 
                $strTextbill = "\n" . $t1 . "|" . $t2 . "|" . $t3 . "|" . $t4 . "|" . $t5 . "|" . $t6 . "|" . $t7 . "|" . $t8 . "|" . $t9 . "|" . $t10 . "|" . $t11 . "|" . $t12 . "|" . $t13 . "|" . $t14 . "|" . $t15 . "|" . $t16 . "|" . $t17 . "|" . $t18 . "|" . $t19 . "|" . $t20;
                $ansitxt_bitemss = iconv('UTF-8', 'TIS-620', $strTextbill);
                fwrite($objFopen_opd, $ansitxt_bitemss);
            }
            $sum_billitems_ = DB::connection('mysql')->select('SELECT SUM(ChargeAmt) as Total from d_abillitems');
            foreach ($sum_billitems_ as $key => $value_sum) {
                $sum_billitems = $value_sum->Total;
            }

            $opd_head = "\n" . '</BillItems>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<InvAddDiscount>0.00</InvAddDiscount>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<DRGCharge>' . $sum_billitems . '</DRGCharge>';
            fwrite($objFopen_opd, $opd_head);
            $opd_head = "\n" . '<XDRGClaim>0.0000</XDRGClaim>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</Invoices>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '<Coinsurance> </Coinsurance>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n" . '</CIPN>';
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n";
            fwrite($objFopen_opd, $opd_head);

            $opd_head = "\n";
            fwrite($objFopen_opd, $opd_head);


            $md5file = md5_file($file_pat, FALSE);
            $mdup = strtoupper($md5file);
 
            // ********************HASH MD5******************** 
        // }
         
            // ********************File 2  ********************
            $file_pat2 = "Export/" . $folder . "/10978-AIPN-" .$an.'-'. $aipn_date_now_preg . '' . $aipn_time_now_preg . ".xml";
            $objFopen_opd2 = fopen($file_pat2, 'w');

            $opd_head2 = '<?xml version="1.0" encoding="windows-874"?>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<CIPN>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<Header>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<DocClass>IPClaim</DocClass>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<DocSysID version="2.1">AIPN</DocSysID>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<serviceEvent>ADT</serviceEvent>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<authorID>10978</authorID>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<authorName>รพ.ภูเขียวเฉลิมพระเกียรติ</authorName>';
            $opd_head_ansi2 = iconv('UTF-8', 'TIS-620', $opd_head2);
            fwrite($objFopen_opd2, $opd_head_ansi2);

            $opd_head2 = "\n" . '<effectiveTime>' . $aipn_date_now . 'T' . $aipn_time_now . '</effectiveTime>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</Header>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<ClaimAuth>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<AuthCode></AuthCode>';
            fwrite($objFopen_opd2, $opd_head2);

            $aipn_InvNumber_2 = DB::connection('mysql')->select('SELECT AN,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch FROM d_aipadt');
            foreach ($aipn_InvNumber_2 as $key => $val2) {
                $inv2 = $val2->AN;
                $audt2 = $val2->DTAdm;
                $indt2 = $val2->DTDisch;
            }

            $opd_head2 = "\n" . '<AuthDT>' . $audt2 . '</AuthDT>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<UPayPlan>80</UPayPlan>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ServiceType>IP</ServiceType>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ProjectCode></ProjectCode>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<EventCode> </EventCode>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<UserReserve> </UserReserve>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<Hmain>10702</Hmain>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<Hcare>10978</Hcare>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<CareAs>B</CareAs>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<ServiceSubType> </ServiceSubType>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</ClaimAuth>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<IPADT>';
            fwrite($objFopen_opd2, $opd_head2);

            $aipn_data2 = DB::connection('mysql')->select('   
                    SELECT 
                    AN,HN,IDTYPE,PIDPAT,TITLE,NAMEPAT,DOB,SEX,MARRIAGE,CHANGWAT,AMPHUR,NATION,AdmType,AdmSource 
                    ,CONCAT(DTAdm_d,"T",DTAdm_t) as DTAdm
                    ,CONCAT(DTDisch_d,"T",DTDisch_t) as DTDisch 
                    ,LeaveDay,DischStat,DishType,AdmWt,DishWard,Dept
                    FROM d_aipadt 
                ');

            foreach ($aipn_data2 as $key => $value22) {
                $bb1 = $value22->AN;
                $bb2 = $value22->HN;
                $bb3 = $value22->IDTYPE;
                $bb4 = $value22->PIDPAT;
                $bb5 = $value22->TITLE;
                $bb6 = $value22->NAMEPAT;
                $bb7 = $value22->DOB;
                $bb8 = $value22->SEX;
                $bb9 = $value22->MARRIAGE;
                $bb10 = $value22->CHANGWAT;
                $bb11 = $value22->AMPHUR;
                $bb12 = $value22->NATION;
                $bb13 = $value22->AdmType;
                $bb14 = $value22->AdmSource;
                $bb15 = $value22->DTAdm;
                $bb16 = $value22->DTDisch;
                $bb17 = $value22->LeaveDay;
                $bb18 = $value22->DischStat;
                $bb19 = $value22->DishType;
                $bb20 = $value22->AdmWt;
                $bb21 = $value22->DishWard;
                $bb22 = $value22->Dept;
                $strText22 = "\n" . $bb1 . "|" . $bb2 . "|" . $bb3 . "|" . $bb4 . "|" . $bb5 . "|" . $bb6 . "|" . $bb7 . "|" . $bb8 . "|" . $bb9 . "|" . $bb10 . "|" . $bb11 . "|" . $bb12 . "|" . $bb13 . "|" . $bb14 . "|" . $bb15 . "|" . $bb16 . "|" . $bb17 . "|" . $bb18 . "|" . $bb19 . "|" . $bb20 . "|" . $bb21 . "|" . $bb22;
                $ansitxt_pat22 = iconv('UTF-8', 'TIS-620', $strText22);
                fwrite($objFopen_opd2, $ansitxt_pat22);
            }

            $opd_head2 = "\n" . '</IPADT>';
            fwrite($objFopen_opd2, $opd_head2);

            $ipdx_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_aipdx_id) as iCount FROM d_aipdx');
            foreach ($ipdx_count_2 as $key => $value_c2) {
                $ipdx_count2 = $value_c2->iCount;
            }
            $opd_head2 = "\n" . '<IPDx Reccount="' . $ipdx_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $ipdx2 = DB::connection('mysql')->select('   
                    SELECT * FROM d_aipdx  
                ');
            foreach ($ipdx2 as $key => $value_ip2) {
                $ss1 = $value_ip2->sequence;
                $ss2 = $value_ip2->DxType;
                $ss3 = $value_ip2->CodeSys;
                $ss4 = $value_ip2->Dcode;
                $ss5 = $value_ip2->DiagTerm;
                $ss6 = $value_ip2->DR;

                $strTexts = "\n" . $ss1 . "|" . $ss2 . "|" . $ss3 . "|" . $ss4 . "|" . $ss5 . "|" . $ss6 . "|";
                $ansitxt_ipdxs = iconv('UTF-8', 'TIS-620', $strTexts);
                fwrite($objFopen_opd2, $ansitxt_ipdxs);
            }
            $opd_head2 = "\n" . '</IPDx>';
            fwrite($objFopen_opd2, $opd_head2);

            $ipop_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_aipop_id) as iopcount FROM d_aipop');
            foreach ($ipop_count_2 as $key => $value_op2) {
                $ipop_count2 = $value_op2->iopcount;
            }
            $opd_head2 = "\n" . '<IPOp Reccount="' . $ipop_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $ipop2 = DB::connection('mysql')->select('   
                    SELECT 
                    sequence,CodeSys,Code,Procterm,DR,DateIn,DateOut,Location 
                    FROM d_aipop  
                ');
            foreach ($ipop2 as $key => $value_ipop2) {
                $so1 = $value_ipop2->sequence;
                $so2 = $value_ipop2->CodeSys;
                $so3 = $value_ipop2->Code;
                $so4 = $value_ipop2->Procterm;
                $so5 = $value_ipop2->DR;
                $so6 = $value_ipop2->DateIn;
                $so7 = $value_ipop2->DateOut;

                $strTexto = "\n" . $so1 . "|" . $so2 . "|" . $so3 . "|" . $so4 . "|" . $so5 . "|" . $so6 . "|" . $so7 . "|";
                $ansitxt_ipopoo = iconv('UTF-8', 'TIS-620', $strTexto);
                fwrite($objFopen_opd2, $ansitxt_ipopoo);
            }
            $opd_head2 = "\n" . '</IPOp>';
            fwrite($objFopen_opd2, $opd_head2);

            $billitem_count_2 = DB::connection('mysql')->select('SELECT COUNT(d_abillitems_id) as bill_count FROM d_abillitems');
            foreach ($billitem_count_2 as $key => $value_bill2) {
                $billitem_count2 = $value_bill2->bill_count;
            }
            $opd_head2 = "\n" . '<Invoices>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvNumber>' . $inv2 . '</InvNumber>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvDT>' . $indt2 . '</InvDT>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<BillItems Reccount="' . $billitem_count2 . '">';
            fwrite($objFopen_opd2, $opd_head2);

            $text_billitems_2 = DB::connection('mysql')->select('SELECT * from d_abillitems');
            foreach ($text_billitems_2 as $key => $bitem2) {
                $at1 = $bitem2->sequence;
                $at2 = $bitem2->ServDate;
                $at3 = $bitem2->BillGr;
                $at4 = $bitem2->LCCode;
                $at5 = $bitem2->Descript;
                $at6 = $bitem2->QTY;
                $at7 = $bitem2->UnitPrice;
                $at8 = $bitem2->ChargeAmt;
                $at9 = $bitem2->Discount;
                $at10 = $bitem2->ProcedureSeq;
                $at11 = $bitem2->DiagnosisSeq;
                $at12 = $bitem2->ClaimSys;
                $at13 = $bitem2->BillGrCS;
                $at14 = $bitem2->CSCode;
                $at15 = $bitem2->CodeSys;
                $at16 = $bitem2->STDCode;
                $at17 = $bitem2->ClaimCat;
                $at18 = $bitem2->DateRev;
                $at19 = $bitem2->ClaimUP;
                $at20 = $bitem2->ClaimAmt;

                $strTextD22 = "\n" . $at1 . "|" . $at2 . "|" . $at3 . "|" . $at4 . "|" . $at5 . "|" . $at6 . "|" . $at7 . "|" . $at8 . "|" . $at9 . "|" . $at10 . "|" . $at11 . "|" . $at12 . "|" . $at13 . "|" . $at14 . "|" . $at15 . "|" . $at16 . "|" . $at17 . "|" . $at18 . "|" . $at19 . "|" . $at20;
                $ansitxt_bitem2 = iconv('UTF-8', 'TIS-620', $strTextD22);
                fwrite($objFopen_opd2, $ansitxt_bitem2);
            }
            $sum_billitems_a2 = DB::connection('mysql')->select('SELECT SUM(ChargeAmt) as Total from d_abillitems');
            foreach ($sum_billitems_a2 as $key => $value_sum2) {
                $sum_billitemsa2 = $value_sum2->Total;
            }

            $opd_head2 = "\n" . '</BillItems>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<InvAddDiscount>0.00</InvAddDiscount>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<DRGCharge>' . $sum_billitemsa2 . '</DRGCharge>';
            fwrite($objFopen_opd2, $opd_head2);
            $opd_head2 = "\n" . '<XDRGClaim>0.0000</XDRGClaim>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</Invoices>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '<Coinsurance> </Coinsurance>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n" . '</CIPN>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            $objFopen_opd2 = fopen($file_pat2, 'a');
            $opd_head2 = '<?EndNote HMAC="' . $mdup . '" ?>';
            fwrite($objFopen_opd2, $opd_head2);

            $opd_head2 = "\n";
            fwrite($objFopen_opd2, $opd_head2);

            fclose($objFopen_opd2);
        // }
 
        // }

        // return redirect()->route('data.six');
        // return redirect()->back();
        return response()->json([
            'status'    => '200'
        ]);
    }

}
