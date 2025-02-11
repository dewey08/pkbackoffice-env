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
use Illuminate\Support\Facades\File;
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
use App\Models\Products_vendor;
use App\Models\Status; 
use App\Models\Products_request;
use App\Models\Products_request_sub;   
use App\Models\Leave_leader;
use App\Models\Leave_leader_sub;
use App\Models\Book_type;
use App\Models\Check_sit;
use App\Models\Ssop_stm;
use App\Models\Ssop_session;
use App\Models\Ssop_opdx;
use App\Models\Pang_stamp_temp;
use App\Models\Ssop_token;
use App\Models\Ssop_opservices;
use App\Models\Ssop_dispenseditems;
use App\Models\Ssop_dispensing;
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
use Stevebauman\Location\Facades\Location; 
use SoapClient; 
use SplFileObject;
use App\Models\D_dru;
use App\Models\D_adp;
use App\Models\D_aer;
use App\Models\D_cha;  
use App\Models\D_cht;
use App\Models\D_idx;
use App\Models\D_iop;
use App\Models\D_ipd;
use App\Models\D_opd;
use App\Models\D_oop;
use App\Models\D_odx;
use App\Models\D_orf;
use App\Models\D_pat;
use App\Models\D_ins; 
use App\Models\D_irf;
use App\Models\D_lvd;
use App\Models\Export_temp;
 
 

class AncController extends Controller
{
    public function anc_dent(Request $request)
    {
        $datestart = $request->startdate;
        $dateend = $request->enddate;
     
        $ins = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_ins 
        '); 
        $opd = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_opd 
        '); 
        $odx = DB::connection('mysql7')->select('   
            SELECT * FROM Claim_sixteen_odx 
        ');
        $oop = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_oop 
        ');
        $data_ = DB::connection('mysql7')->select('   
        SELECT * FROM export_temp 
        ');
         
        return view('claim.anc_dent',[
            'start'   => $datestart,
            'end'     => $dateend,
            'ins'     => $ins,
            'opd'     => $opd, 
            'odx'     => $odx,
            'oop'     => $oop,
            'data_'     => $data_,
        ]);
    }
    public function anc_dent_pull(Request $request)
    { 
        $datestart = $request->startdate;
        $dateend = $request->enddate;
        // INSERT INTO claim.export_temp
        $dent_opd = DB::connection('mysql3')->select('                      
                SELECT v.vn,oo.an,v.hn,pt.cid,concat(pt.pname,pt.fname," ",pt.lname) as fullname   
                ,"" as created_at
                ,"" as updated_at	 
                from person_anc a
                join person p on p.person_id=a.person_id 
                join patient pt on pt.cid=p.cid
                left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp 
                left join ovstdiag o on o.vn=dt.vn 
                inner JOIN hos.icd9cm1 ic on ic.code = o.icd10
                LEFT JOIN hos.doctor d on d.code = o.doctor
                left join opitemrece oo on oo.vn=o.vn
                left join nondrugitems nd on nd.icode=oo.icode 
                LEFT JOIN vn_stat v on v.vn = o.vn 
                LEFT JOIN hos.pttype ptt on ptt.pttype = v.pttype
                left join hos.ipt_pttype ap on ap.an = oo.an
                left join hos.visit_pttype vp on vp.vn = v.vn
                LEFT JOIN hos.rcpt_debt r on r.vn = v.vn 
                where TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
                and (a.labor_date is null or a.labor_date=" ")
                and p.nationality="99" 
                GROUP BY p.cid 
                order by vn;
        ');
        Export_temp::truncate();
        foreach ($dent_opd as $key => $value) {           
            $add= new Export_temp();
            $add->vn = $value->vn ;
            $add->an = $value->an; 
            $add->hn = $value->hn; 
            $add->cid = $value->cid;
            $add->fullname = $value->fullname; 
            $add->save();
        }
        // return redirect()->back();
        $data_ = DB::connection('mysql7')->select('   
            SELECT * FROM export_temp 
        ');
        $ins = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_ins 
        '); 
        $opd = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_opd 
        '); 
        $odx = DB::connection('mysql7')->select('   
            SELECT * FROM Claim_sixteen_odx 
        ');
        $oop = DB::connection('mysql7')->select('   
            SELECT * FROM claim_sixteen_oop 
        ');
        return view('claim.anc_dent',[
            'start'   => $datestart,
            'end'     => $dateend,
            'data_'     => $data_,
            'ins'     => $ins,
            'opd'     => $opd, 
            'odx'     => $odx,
            'oop'     => $oop,
        ]);
    }
    public function anc_dent_search(Request $request)
    { 
        $datestart = $request->startdate;
        $dateend = $request->enddate;
        $dent_opd = DB::connection('mysql3')->select('   
                SELECT 
                    pt.hn as HN
                    ,v.spclty as CLINIC
                    ,DATE_FORMAT(v.vstdate, "%Y%m%d") DATEOPD
                    ,concat(substr(o.vsttime,1,2),substr(o.vsttime,4,2)) TIMEOPD
                    ,if(dt.vstdate<"2019-09-01","",getwordnum(group_concat(if(d.icd10tm_operation_code in ("2387010", "2277310", "2277320", "2287310", "2287320"),dt.vn,null) order by dt.vn),1)) as SEQ	 
                    ,"1" UUC 
                    from person_anc a
                    join person p on p.person_id=a.person_id 
                    join patient pt on pt.cid=p.cid
                    left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                    LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp
                    LEFT JOIN dttm d on d.code=dt.tmcode
                    left join ovstdiag o on o.vn=dt.vn
                    left join vn_stat v on v.vn=o.vn
                    left join pttype t on t.pttype=v.pttype
                    left join opitemrece oo on oo.vn=o.vn
                    left join nondrugitems nd on nd.icode=oo.icode
                    left join test.tmpeclaim e on e.vn=v.vn
                    left join test.tmprep r on r.vn=o.vn
                    left join test.tmpnhso n on n.vn=o.vn
                    left join pttype ptt on ptt.pttype=v.pttype
                    where   TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
                    and (a.labor_date is null or a.labor_date=" ")
                    and p.nationality="99" 
                    GROUP BY p.cid 
                    order by SEQ; 
        ');
        Claim_sixteen_opd::truncate();
        foreach ($dent_opd as $key => $value) {           
            $add= new Claim_sixteen_opd();
            $add->HN = $value->HN ;
            $add->CLINIC = $value->CLINIC; 
            $add->DATEOPD = $value->DATEOPD; 
            $add->TIMEOPD = $value->TIMEOPD;
            $add->SEQ = $value->SEQ;
            $add->UUC = $value->UUC;
            $add->save();
        }
        $dent_ins = DB::connection('mysql3')->select('   
            SELECT v.hn HN
                    ,if(oo.an is null,ptt.hipdata_code,ptt.hipdata_code) INSCL
                    ,if(oo.an is null,ptt.pcode,ptt.pcode) SUBTYPE
                    ,v.cid CID
                    ,DATE_FORMAT(if(oo.an is null,v.pttype_begin,ap.begin_date), "%Y%m%d") DATEIN
                    ,DATE_FORMAT(if(oo.an is null,v.pttype_expire,ap.expire_date), "%Y%m%d") DATEEXP
                    ,if(oo.an is null,v.hospmain,ap.hospmain) HOSPMAIN
                    ,if(oo.an is null,v.hospsub,ap.hospsub) HOSPSUB
                    ,"" GOVCODE
                    ,"" GOVNAME
                    ,ifnull(if(oo.an is null,vp.claim_code,ap.claim_code),r.sss_approval_code) PERMITNO
                    ,"" DOCNO
                    ,"" OWNRPID 
                    ,"" OWNRNAME
                    ,oo.an AN
                    ,o.vn SEQ
                    ,"" SUBINSCL 
                    ,"" RELINSCL
                    ,"" HTYPE
                    
                    from person_anc a
                    join person p on p.person_id=a.person_id 
                    join patient pt on pt.cid=p.cid
                    left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                    LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp
                    LEFT JOIN dttm d on d.code=dt.tmcode
                    left join ovstdiag o on o.vn=dt.vn 
                    left join opitemrece oo on oo.vn=o.vn
                    left join nondrugitems nd on nd.icode=oo.icode 
                     LEFT JOIN vn_stat v on v.vn = o.vn 
                    LEFT JOIN hos.pttype ptt on ptt.pttype = v.pttype
                    left join hos.ipt_pttype ap on ap.an = oo.an
                    left join hos.visit_pttype vp on vp.vn = v.vn
                    LEFT JOIN hos.rcpt_debt r on r.vn = v.vn  
                 
                where   TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
                and (a.labor_date is null or a.labor_date=" ")
                and p.nationality="99" 
                GROUP BY p.cid 
                order by SEQ;
        ');
        
        Claim_sixteen_ins::truncate();
        foreach ($dent_ins as $key => $value2) {           
            $add2= new Claim_sixteen_ins();
            $add2->HN = $value2->HN ;
            $add2->INSCL = $value2->INSCL; 
            $add2->SUBTYPE = $value2->SUBTYPE; 
            $add2->CID = $value2->CID;
            $add2->DATEIN = $value2->DATEIN;
            $add2->DATEEXP = $value2->DATEEXP;
            $add2->HOSPMAIN = $value2->HOSPMAIN;
            $add2->HOSPSUB = $value2->HOSPSUB;
            $add2->GOVCODE = $value2->GOVCODE;
            $add2->GOVNAME = $value2->GOVNAME;
            $add2->PERMITNO = $value2->PERMITNO;
            $add2->DOCNO = $value2->DOCNO;
            $add2->OWNRPID = $value2->OWNRPID;
            $add2->OWNRNAME = $value2->OWNRNAME;
            $add2->AN = $value2->AN;
            $add2->SEQ = $value2->SEQ;
            $add2->SUBINSCL = $value2->SUBINSCL;
            $add2->RELINSCL = $value2->RELINSCL;
            $add2->HTYPE = $value2->HTYPE;
            $add2->save();
        }

        // $ssop_pat = DB::connection('mysql3')->select('   
        //     SELECT v.hcode HCODE
        //         ,v.hn HN
        //         ,pt.chwpart CHANGWAT
        //         ,pt.amppart AMPHUR
        //         ,DATE_FORMAT(pt.birthday, "%Y%m%d") DOB
        //         ,pt.sex SEX
        //         ,pt.marrystatus MARRIAGE 
        //         ,pt.occupation OCCUPA
        //         ,lpad(pt.nationality,3,0) NATION
        //         ,pt.cid PERSON_ID
        //         ,concat(pt.fname," ",pt.lname,",",pt.pname) NAMEPAT
        //         ,pt.pname TITLE
        //         ,pt.fname FNAME 
        //         ,pt.lname LNAME
        //         ,"1" IDTYPE
        //         ,v.vstdate 
        //         from vn_stat v
        //         LEFT JOIN hos.pttype p on p.pttype = v.pttype
        //         LEFT JOIN hos.ipt i on i.vn = v.vn 
        //         LEFT JOIN hos.patient pt on pt.hn = v.hn

        //         join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn   
                
        //         WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //         AND claim.claim_temp_ssop.CHECK="Y"
        // ');
        // Claim_sixteen_pat::truncate();
        // foreach ($ssop_pat as $key => $value3) {           
        //     $add3= new Claim_sixteen_pat();
        //     $add3->HCODE = $value3->HCODE ;
        //     $add3->HN = $value3->HN; 
        //     $add3->CHANGWAT = $value3->CHANGWAT; 
        //     $add3->AMPHUR = $value3->AMPHUR;
        //     $add3->DOB = $value3->DOB;
        //     $add3->SEX = $value3->SEX;
        //     $add3->MARRIAGE = $value3->MARRIAGE;
        //     $add3->OCCUPA = $value3->OCCUPA;
        //     $add3->NATION = $value3->NATION;
        //     $add3->PERSON_ID = $value3->PERSON_ID;
        //     $add3->NAMEPAT = $value3->NAMEPAT;
        //     $add3->TITLE = $value3->TITLE;
        //     $add3->FNAME = $value3->FNAME;
        //     $add3->LNAME = $value3->LNAME; 
        //     $add3->IDTYPE = $value3->IDTYPE; 
        //     $add3->save();
        // }

        // $ssop_orf = DB::connection('mysql3')->select('   
        //         SELECT v.hn HN
        //         ,DATE_FORMAT(v.vstdate, "%Y%m%d") DATEOPD
        //         ,v.spclty CLINIC
        //         ,ifnull(r1.refer_hospcode,r2.refer_hospcode) REFER
        //         ,"0100" REFERTYPE
        //         ,v.vn SEQ
        //         ,v.vstdate 
        //         from hos.vn_stat v
        //         LEFT JOIN hos.pttype p on p.pttype = v.pttype
        //         LEFT JOIN hos.referin r1 on r1.vn = v.vn 
        //         LEFT JOIN hos.referout r2 on r2.vn = v.vn

        //         join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn   
                
        //         WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //         AND claim.claim_temp_ssop.CHECK="Y"
        // ');
        // Claim_sixteen_orf::truncate();
        // foreach ($ssop_orf as $key => $value4) {           
        //     $add4= new Claim_sixteen_orf(); 
        //     $add4->HN = $value4->HN; 
        //     $add4->DATEOPD = $value4->DATEOPD; 
        //     $add4->CLINIC = $value4->CLINIC;
        //     $add4->REFER = $value4->REFER;
        //     $add4->REFERTYPE = $value4->REFERTYPE;
        //     $add4->SEQ = $value4->SEQ; 
        //     $add4->save();
        // }

        $dent_odx = DB::connection('mysql3')->select('   
            SELECT v.hn as HN
            ,DATE_FORMAT(v.vstdate,"%Y%m%d") as DATEDX
            ,v.spclty as CLINIC
            ,o.icd10 as DIAG
            ,o.diagtype as DXTYPE
            ,if(d.licenseno=" ","-99999",d.licenseno) as DRDX
            ,v.cid as PERSON_ID 
            ,if(dt.vstdate<"2019-09-01","",getwordnum(group_concat(if(dm.icd10tm_operation_code in ("2387010", "2277310", "2277320", "2287310", "2287320"),dt.vn,null) order by dt.vn),1)) as SEQ
            ,v.vstdate 

                from person_anc a
                join person p on p.person_id=a.person_id 
                join patient pt on pt.cid=p.cid
                left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp 
                LEFT JOIN dttm dm on dm.code=dt.tmcode
                left join ovstdiag o on o.vn=dt.vn 
                LEFT JOIN hos.doctor d on d.`code` = o.doctor
                left join opitemrece oo on oo.vn=o.vn
                left join nondrugitems nd on nd.icode=oo.icode 
                LEFT JOIN vn_stat v on v.vn = o.vn 
                LEFT JOIN hos.pttype ptt on ptt.pttype = v.pttype
                left join hos.ipt_pttype ap on ap.an = oo.an
                left join hos.visit_pttype vp on vp.vn = v.vn
                LEFT JOIN hos.rcpt_debt r on r.vn = v.vn  
                 
                WHERE TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'"
                and (a.labor_date is null or a.labor_date=" ")
                and p.nationality="99" 
                GROUP BY p.cid 
                order by SEQ;
        ');
        Claim_sixteen_odx::truncate();
        foreach ($dent_odx as $key => $value5) {           
            $add5 = new Claim_sixteen_odx(); 
            $add5->HN = $value5->HN; 
            $add5->DATEDX = $value5->DATEDX; 
            $add5->CLINIC = $value5->CLINIC;
            $add5->DIAG = $value5->DIAG;
            $add5->DXTYPE = $value5->DXTYPE;
            $add5->DRDX = $value5->DRDX;
            $add5->PERSON_ID = $value5->PERSON_ID; 
            $add5->SEQ = $value5->SEQ; 
            $add5->save();
        }

        $dent_oop = DB::connection('mysql3')->select('   
                SELECT v.hn HN
                ,DATE_FORMAT(v.vstdate,"%Y%m%d") DATEOPD
                ,v.spclty CLINIC
                ,o.icd10 OPER
                ,if(d.licenseno=" ","-99999",d.licenseno) DROPID
                ,pt.cid PERSON_ID 
                ,v.vn SEQ
                ,v.vstdate 
                from person_anc a
                join person p on p.person_id=a.person_id 
                join patient pt on pt.cid=p.cid
                left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp 
                left join ovstdiag o on o.vn=dt.vn 
                inner JOIN hos.icd9cm1 ic on ic.code = o.icd10
                LEFT JOIN hos.doctor d on d.code = o.doctor
                left join opitemrece oo on oo.vn=o.vn
                left join nondrugitems nd on nd.icode=oo.icode 
                LEFT JOIN vn_stat v on v.vn = o.vn 
                LEFT JOIN hos.pttype ptt on ptt.pttype = v.pttype
                left join hos.ipt_pttype ap on ap.an = oo.an
                left join hos.visit_pttype vp on vp.vn = v.vn
                LEFT JOIN hos.rcpt_debt r on r.vn = v.vn  
                 
                where TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'"
                and (a.labor_date is null or a.labor_date=" ")
                and p.nationality="99" 
                GROUP BY p.cid 
                order by SEQ;
        ');
        Claim_sixteen_oop::truncate();
        foreach ($dent_oop as $key => $value6) {           
            $add6 = new Claim_sixteen_oop(); 
            $add6->HN = $value6->HN; 
            $add6->DATEOPD = $value6->DATEOPD; 
            $add6->CLINIC = $value6->CLINIC;
            $add6->OPER = $value6->OPER;
            $add6->DROPID = $value6->DROPID;
            $add6->PERSON_ID = $value6->PERSON_ID; 
            $add6->SEQ = $value6->SEQ; 
            $add6->save();
        }

        // $ssop_cht = DB::connection('mysql3')->select('   
        //         SELECT v.hn HN
        //         ,v.an AN
        //         ,DATE_FORMAT(if(a.an is null,v.vstdate,a.dchdate),"%Y%m%d") DATE
        //         ,round(if(a.an is null,vv.income,a.income),2) TOTAL
        //         ,round(if(a.an is null,vv.paid_money,a.paid_money),2) PAID
        //         ,if(vv.paid_money >"0" or a.paid_money >"0","10",pt.pcode) PTTYPE
        //         ,pp.cid PERSON_ID  
        //         ,v.vn SEQ
        //         ,v.vstdate 
        //         from hos.ovst v
        //         LEFT JOIN hos.vn_stat vv on vv.vn = v.vn
        //         LEFT JOIN hos.an_stat a on a.an = v.an
        //         LEFT JOIN hos.patient pp on pp.hn = v.hn
        //         LEFT JOIN hos.pttype pt on pt.pttype = vv.pttype or pt.pttype=a.pttype
        //         LEFT JOIN hos.pttype p on p.pttype = a.pttype

        //         join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn   
                
        //         WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //         AND claim.claim_temp_ssop.CHECK="Y"
        // ');
        // Claim_sixteen_cht::truncate();
        // foreach ($ssop_cht as $key => $value7) {           
        //     $add7 = new Claim_sixteen_cht(); 
        //     $add7->HN = $value7->HN; 
        //     $add7->AN = $value7->AN;
        //     $add7->DATE = $value7->DATE; 
        //     $add7->TOTAL = $value7->TOTAL;
        //     $add7->PAID = $value7->PAID;
        //     $add7->PTTYPE = $value7->PTTYPE;
        //     $add7->PERSON_ID = $value7->PERSON_ID; 
        //     $add7->SEQ = $value7->SEQ; 
        //     $add7->save();
        // }

        // $ssop_cha = DB::connection('mysql3')->select('   
             
        //         SELECT v.hn HN
        //             ,if(v1.an is null," ",v1.an) AN 
        //             ,if(v1.an is null,DATE_FORMAT(v.vstdate,"%Y%m%d"),DATE_FORMAT(v1.dchdate,"%Y%m%d")) DATE
        //             ,if(v.paidst in("01","03"),dx.chrgitem_code2,dc.chrgitem_code1) CHRGITEM
        //             ,round(sum(v.sum_price),2) AMOUNT
        //             ,p.cid PERSON_ID 
        //             ,ifnull(v.vn,v.an) SEQ
        //             from hos.opitemrece v
        //             LEFT JOIN hos.vn_stat vv on vv.vn = v.vn
        //             LEFT JOIN hos.patient p on p.hn = v.hn
        //             LEFT JOIN hos.ipt v1 on v1.an = v.an
        //             LEFT JOIN hos.income i on v.income=i.income
        //             LEFT JOIN hos.drg_chrgitem dc on i.drg_chrgitem_id=dc.drg_chrgitem_id 
        //             LEFT JOIN hos.drg_chrgitem dx on i.drg_chrgitem_id= dx.drg_chrgitem_id 

        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn 

        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             AND claim.claim_temp_ssop.CHECK="Y"
        //             group by v.vn,CHRGITEM

        //             union all
        //             SELECT v.hn HN
        //             ,v1.an AN 
        //             ,if(v1.an is null,DATE_FORMAT(v.vstdate,"%Y%m%d"),DATE_FORMAT(v1.dchdate,"%Y%m%d")) DATE
        //             ,if(v.paidst in("01","03"),dx.chrgitem_code2,dc.chrgitem_code1) CHRGITEM
        //             ,round(sum(v.sum_price),2) AMOUNT
        //             ,p.cid PERSON_ID 
        //             ,ifnull(v.vn,v.an) SEQ
        //             from hos.opitemrece v
        //             LEFT JOIN hos.vn_stat vv on vv.vn = v.vn
        //             LEFT JOIN hos.patient p on p.hn = v.hn
        //             LEFT JOIN hos.ipt v1 on v1.an = v.an
        //             LEFT JOIN hos.income i on v.income=i.income
        //             LEFT JOIN hos.drg_chrgitem dc on i.drg_chrgitem_id=dc.drg_chrgitem_id 
        //             LEFT JOIN hos.drg_chrgitem dx on i.drg_chrgitem_id= dx.drg_chrgitem_id 

        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn 
        //             AND claim.claim_temp_ssop.CHECK="Y"

        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             group by v.an,CHRGITEM;

        // ');
        // Claim_sixteen_cha::truncate();
        // foreach ($ssop_cha as $key => $value8) {           
        //     $add8 = new Claim_sixteen_cha(); 
        //     $add8->HN = $value8->HN; 
        //     $add8->AN = $value8->AN;
        //     $add8->DATE = $value8->DATE; 
        //     $add8->CHRGITEM = $value8->CHRGITEM;
        //     $add8->AMOUNT = $value8->AMOUNT; 
        //     $add8->PERSON_ID = $value8->PERSON_ID; 
        //     $add8->SEQ = $value8->SEQ; 
        //     $add8->save();
        // }

        // $dent_adp = DB::connection('mysql3')->select(' 
        //     SELECT HN,AN,DATEOPD,TYPE,CODE,sum(QTY) QTY
        //             ,RATE,SEQ
        //             ," " a1," " a2," " a3," " a4,"0" a5," " a6,"0" a7 ," " a8
        //             ," " TMLTCODE
        //             ," " STATUS1
        //             ," " BI
        //             ," " CLINIC
        //             ," " ITEMSRC
        //             ," " PROVIDER
        //             ," " GLAVIDA
        //             ," " GA_WEEK
        //             ," " DCIP
        //             ,"0000-00-00" LMP
        //             from (SELECT v.hn HN
        //             ,v.an AN
        //             ,DATE_FORMAT(v.rxdate,"%Y%m%d") DATEOPD
        //             ,n.nhso_adp_type_id TYPE
        //             ,n.nhso_adp_code CODE 
        //             ,sum(v.QTY) QTY
        //             ,round(v.unitprice,2) RATE
        //             ,if(v.an is null,v.vn," ") SEQ
        //             ," " a1," " a2," " a3," " a4," " a5," " a6," " a7 ," " a8
        //             ," " TMLTCODE
        //             ," " STATUS1
        //             ," " BI
        //             ," " CLINIC
        //             ," " ITEMSRC
        //             ," " PROVIDER
        //             ," " GLAVIDA
        //             ," " GA_WEEK
        //             ," " DCIP
        //             ,"0000-00-00" LMP
        //             from hos.opitemrece v
        //             inner JOIN hos.drugitems n on n.icode = v.icode and n.nhso_adp_code is not null
        //             left join hos.ipt i on i.an = v.an
        //             AND i.an is not NULL

        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn 
        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             AND claim.claim_temp_ssop.CHECK="Y"
                   

        //             GROUP BY i.vn,n.nhso_adp_code,rate) a 
        //             GROUP BY an,CODE,rate
        //             UNION
        //             SELECT HN,AN,DATEOPD,TYPE,CODE,sum(QTY) QTY,RATE,SEQ," " " " a1," " a2," " a3," " a4,"0" a5," " a6,"0" a7 ," " a8
        //             ," "TMLTCODE
        //             ," " STATUS1
        //             ," " BI
        //             ," " CLINIC
        //             ," " ITEMSRC
        //             ," " PROVIDER
        //             ," " GLAVIDA
        //             ," " GA_WEEK
        //             ," " DCIP
        //             ,"0000-00-00" LMP
        //             from
        //             (SELECT v.hn HN
        //             ,v.an AN
        //             ,DATE_FORMAT(v.vstdate,"%Y%m%d") DATEOPD
        //             ,n.nhso_adp_type_id TYPE
        //             ,n.nhso_adp_code CODE 
        //             ,sum(v.QTY) QTY
        //             ,round(v.unitprice,2) RATE
        //             ,if(v.an is null,v.vn," ") SEQ
        //             ," " a1," " a2," " a3," " a4,"0" a5,"" a6,"0" a7 ,"" a8
        //             ," " TMLTCODE
        //             ," " STATUS1
        //             ," " BI
        //             ," " CLINIC
        //             ," " ITEMSRC
        //             ," " PROVIDER
        //             ," " GLAVIDA
        //             ," " GA_WEEK
        //             ," " DCIP
        //             ,"0000-00-00" LMP
        //             from hos.opitemrece v
        //             inner JOIN hos.drugitems n on n.icode = v.icode and n.nhso_adp_code is not null
        //             left join hos.ipt i on i.an = v.an
                   
        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn 
        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             AND claim.claim_temp_ssop.CHECK="Y"

        //             AND i.an is NULL
        //             GROUP BY v.vn,n.nhso_adp_code,rate) b 
        //             GROUP BY seq,CODE,rate 
 

        // ');
        // claim_sixteen_adp::truncate();
        // foreach ($dent_adp as $key => $value8) {           
        //     $add8 = new claim_sixteen_adp(); 
        //     $add8->HN = $value8->HN; 
        //     $add8->AN = $value8->AN;
        //     $add8->DATE = $value8->DATE; 
        //     $add8->CHRGITEM = $value8->CHRGITEM;
        //     $add8->AMOUNT = $value8->AMOUNT; 
        //     $add8->PERSON_ID = $value8->PERSON_ID; 
        //     $add8->SEQ = $value8->SEQ; 
        //     $add8->save();
        // }

        // $ssop_dru = DB::connection('mysql3')->select('   
        //         SELECT vv.hcode HCODE
        //             ,v.hn HN
        //             ,v.an AN
        //             ,vv.spclty CLINIC
        //             ,vv.cid PERSON_ID
        //             ,DATE_FORMAT(v.vstdate,"%Y%m%d") DATE_SERV
        //             ,d.icode DID
        //             ,concat(d.name," ",d.strength," ",d.units) DIDNAME
        //             ,v.qty AMOUNT
        //             ,round(v.unitprice,2) DRUGPRIC
        //             ,"0.00" DRUGCOST
        //             ,d.did DIDSTD
        //             ,d.units UNIT
        //             ,concat(d.packqty,"x",d.units) UNIT_PACK
        //             ,v.vn SEQ
        //             ,oo.presc_reason DRUGREMARK
        //             ," "PA_NO
        //             ," " TOTCOPAY
        //             ,if(v.item_type="H","2","1") USE_STATUS
        //             ," " TOTAL," " a1," "  a2
        //             from hos.opitemrece v
        //             LEFT JOIN hos.drugitems d on d.icode = v.icode
        //             LEFT JOIN hos.vn_stat vv on vv.vn = v.vn
        //             LEFT JOIN hos.ovst_presc_ned oo on oo.vn = v.vn and oo.icode=v.icode
                    
        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v.vn               
        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             AND claim.claim_temp_ssop.CHECK="Y"
        //             and d.did is not null 
        //             GROUP BY v.vn,did

        //             UNION all
        //             SELECT pt.hcode HCODE
        //             ,v.hn HN
        //             ,v.an AN
        //             ,v1.spclty CLINIC
        //             ,pt.cid PERSON_ID
        //             ,DATE_FORMAT((v.vstdate),"%Y%m%d") DATE_SERV
        //             ,d.icode DID
        //             ,concat(d.name," ",d.strength," ",d.units) DIDNAME
        //             ,sum(v.qty) AMOUNT
        //             ,round(v.unitprice,2) DRUGPRIC
        //             ,"0.00" DRUGCOST
        //             ,d.did DIDSTD
        //             ,d.units UNIT
        //             ,concat(d.packqty,"x",d.units) UNIT_PACK
        //             ,ifnull(v.vn,v.an) SEQ
        //             ,oo.presc_reason DRUGREMARK
        //             ," " PA_NO
        //             ," " TOTCOPAY
        //             ,if(v.item_type="H","2","1") USE_STATUS
        //             ," " TOTAL," " a1," "  a2
        //             from hos.opitemrece v
        //             LEFT JOIN hos.drugitems d on d.icode = v.icode
        //             LEFT JOIN hos.patient pt  on v.hn = pt.hn
        //             inner JOIN hos.ipt v1 on v1.an = v.an
        //             LEFT JOIN hos.ovst_presc_ned oo on oo.vn = v.vn and oo.icode=v.icode
 
        //             join claim.claim_temp_ssop on claim.claim_temp_ssop.SEQ = v1.vn               
        //             WHERE v.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
        //             AND claim.claim_temp_ssop.CHECK="Y"

        //             and d.did is not null AND v.qty<>"0"
        //             GROUP BY v.an,d.icode,USE_STATUS
 
 
        // ');
        // Claim_sixteen_dru::truncate();
        // foreach ($ssop_dru as $key => $value9) {           
        //     $add9 = new Claim_sixteen_dru(); 
        //     $add9->HCODE = $value9->HCODE; 
        //     $add9->HN = $value9->HN;
        //     $add9->AN = $value9->AN; 
        //     $add9->CLINIC = $value9->CLINIC;
        //     $add9->PERSON_ID = $value9->PERSON_ID;
        //     $add9->DATE_SERV = $value9->DATE_SERV;
        //     $add9->DID = $value9->DID; 
        //     $add9->DIDNAME = $value9->DIDNAME;
        //     $add9->AMOUNT = $value9->AMOUNT; 
        //     $add9->DRUGPRIC = $value9->DRUGPRIC;
        //     $add9->DRUGCOST = $value9->DRUGCOST;
        //     $add9->DIDSTD = $value9->DIDSTD;
        //     $add9->UNIT = $value9->UNIT;
        //     $add9->UNIT_PACK = $value9->UNIT_PACK;
        //     $add9->SEQ = $value9->SEQ;
        //     $add9->DRUGREMARK = $value9->DRUGREMARK;
        //     $add9->PA_NO = $value9->PA_NO;
        //     $add9->TOTCOPAY = $value9->TOTCOPAY;
        //     $add9->USE_STATUS = $value9->USE_STATUS;
        //     // $add9->STATUS1 = $value9->STATUS1;
        //     $add9->TOTAL = $value9->TOTAL;
        //     $add9->a1 = $value9->a1;
        //     $add9->a2 = $value9->a2;
        //     $add9->save();
        // }
            
            return Redirect()->route('claim.anc_dent'); 
       
    }
         
    public function anc_dent_insert(Request $request)
    { 
        $datestart = $request->startdate;
        $dateend = $request->enddate;
        $dent_opd = DB::connection('mysql3')->select('   
                SELECT 
                    pt.hn as HN
                    ,v.spclty as CLINIC
                    ,DATE_FORMAT(v.vstdate, "%Y%m%d") DATEOPD
                    ,concat(substr(o.vsttime,1,2),substr(o.vsttime,4,2)) TIMEOPD
                    ,if(dt.vstdate<"2019-09-01","",getwordnum(group_concat(if(d.icd10tm_operation_code in ("2387010", "2277310", "2277320", "2287310", "2287320"),dt.vn,null) order by dt.vn),1)) as SEQ	 
                    ,"1" UUC 
                    from person_anc a
                    join person p on p.person_id=a.person_id 
                    join patient pt on pt.cid=p.cid
                    left join person_anc_service  ps on ps.person_anc_id=a.person_anc_id 
                    LEFT JOIN dtmain dt on dt.hn=pt.hn  and dt.vstdate>a.lmp
                    LEFT JOIN dttm d on d.code=dt.tmcode
                    left join ovstdiag o on o.vn=dt.vn
                    left join vn_stat v on v.vn=o.vn
                    left join pttype t on t.pttype=v.pttype
                    left join opitemrece oo on oo.vn=o.vn
                    left join nondrugitems nd on nd.icode=oo.icode
                    left join test.tmpeclaim e on e.vn=v.vn
                    left join test.tmprep r on r.vn=o.vn
                    left join test.tmpnhso n on n.vn=o.vn
                    left join pttype ptt on ptt.pttype=v.pttype
                    where   TIMESTAMPDIFF(MONTH,a.lmp,now())<=10  and dt.vstdate BETWEEN "'.$datestart.'" AND "'.$dateend.'" 
                    and (a.labor_date is null or a.labor_date=" ")
                    and p.nationality="99" 
                    GROUP BY p.cid 
                    order by SEQ; 
        ');
        
            return Redirect()->route('claim.anc_dent'); 
       
    }

    public function prenatal_care(Request $request)
    {
        $startdate = $request->startdate;
        $enddate = $request->enddate;
        $yearnew = date('Y')+1;
        $yearold = date('Y')-1;
        $start = (''.$yearold.'-10-01');
        $end = (''.$yearnew.'-09-30'); 
        if ($startdate =='') {
            $data['data_anc'] = DB::connection('mysql2')->select('   
                SELECT w.ward,w.`name` as wardname
                ,count(distinct(i.an)) as total_an
                ,sum(i.adjrw)as sum_adjrw
                ,sum(i.adjrw)/count(distinct(i.an)) as total_cmi
                ,count(DISTINCT(ii.an))as total_noadjre 
                FROM ipt i
                LEFT JOIN an_stat a  on  i.an=a.an
                LEFT JOIN ward  w  on  i.ward=w.ward
                #LEFT JOIN ward_backup1872566  ww on  i.ward=ww.ward
                LEFT JOIN ipt  ii  on  i.an=ii.an  and  ii.adjrw = ""
                WHERE i.dchdate  BETWEEN "'.$start.'" AND "'.$end.'"
                GROUP BY i.ward
            '); 
        } else {
            $data['data_anc'] = DB::connection('mysql2')->select('   
                SELECT w.ward,w.`name` as wardname
                ,count(distinct(i.an))as total_an
                ,sum(i.adjrw)as sum_adjrw
                ,sum(i.adjrw)/count(distinct(i.an)) as total_cmi
                ,count(DISTINCT(ii.an))as total_noadjre 
                FROM ipt i
                LEFT JOIN an_stat a  on  i.an=a.an
                LEFT JOIN ward  w  on  i.ward=w.ward
                #LEFT JOIN ward_backup1872566  ww on  i.ward=ww.ward
                LEFT JOIN ipt  ii  on  i.an=ii.an  and  ii.adjrw = ""
                WHERE i.dchdate  BETWEEN "'.$startdate.'" AND "'.$enddate.'"
                GROUP BY i.ward
            '); 
        }
         
        return view('anc.prenatal_care',$data,[
            'startdate'   => $startdate,
            'enddate'     => $enddate, 
            'start'       => $start,
            'end'         => $end, 
        ]);
    }

    public function prenatal_care_sub(Request $request,$ward,$startdate,$enddate)
    {  
            $data['data_anc'] = DB::connection('mysql2')->select('   
                SELECT  d.name as doctor,w.ward,w.`name` as wardname
                    ,count(distinct(i.an))as total_an
                    ,sum(i.adjrw)as sum_adjrw
                    ,sum(i.adjrw)/count(distinct(i.an)) as total_cmi
                    ,count(DISTINCT(ii.an))as total_noadjre 
                    FROM  ipt i
                    LEFT JOIN an_stat a on  i.an=a.an
                    LEFT JOIN ward w on i.ward=w.ward
                    LEFT JOIN doctor d on i.incharge_doctor=d.`code`
                    #LEFT JOIN ward_backup1872566 ww on i.ward=ww.ward
                    LEFT JOIN ipt ii on i.an=ii.an and ii.adjrw = ""

                    WHERE 
                    i.ward = "'.$ward.'"
                    AND i.dchdate  BETWEEN "'.$startdate.'" AND "'.$enddate.'"
                    GROUP BY  i.incharge_doctor
 
            '); 
         
         
        return view('anc.prenatal_care_sub',$data,[
            'startdate'   => $startdate,
            'enddate'     => $enddate, 
        ]);
    }
   
     

}
