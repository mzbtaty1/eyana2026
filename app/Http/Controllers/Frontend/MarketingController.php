<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Http\Requests\StoreMarketingPriceRequest;
use App\Http\Requests\UpdateMarketingPriceRequest;
use App\Http\Requests\StoreTitleRequest;
use App\Http\Requests\UpdateTitleRequest;
use App\Models\{
    MarketingPrice,
    Title,
    Log
};
class MarketingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $titles = Title::select('*')->orderBy('id','DESC')->where('usage_count' , '!=' , 0)->get();
        $pricesByTitle = $this->pricesGroupedByTitle($titles);
        return view('marketing_prices.all' , ["titles" => $titles, "pricesByTitle" => $pricesByTitle]);
    }



    public function print_all()
    {
        $titles = Title::select('*')->orderBy('id','DESC')->where('usage_count' , '!=' , 0)->get();
        $pricesByTitle = $this->pricesGroupedByTitle($titles);
        return view('marketing_prices.all_print' , ["titles" => $titles, "pricesByTitle" => $pricesByTitle]);
    }

 public function all()
    {
     $titles = Title::select('*')->orderBy('id','DESC')->where('usage_count' , '!=' , 0)->get();
        $pricesByTitle = $this->pricesGroupedByTitle($titles);
        return view('marketing_prices.all2' , ["titles" => $titles, "pricesByTitle" => $pricesByTitle]);
    }

    /**
     * Batched replacement for the per-title MarketingPrice::where('title',...)
     * query that used to run once per title inside each of the three list
     * views (all/all2/all_print). Grouping preserves the travel_date ASC
     * order from the single query, matching what each per-row query did.
     */
    private function pricesGroupedByTitle($titles)
    {
        return MarketingPrice::whereIn('title', $titles->pluck('id'))
            ->orderBy('travel_date', 'ASC')
            ->get()
            ->groupBy('title');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $titles = Title::select('*')->orderBy('id','DESC')->get();
        return view('marketing_prices.create' , ["titles"=>$titles]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMarketingPriceRequest $request)
    {
//        dd($request);
        
        $title_id = $request->title;
        
        $title_info = Title::select('*')->where('id',$title_id)->get();
        abort_if(count($title_info) == 0 , 404);
        $title_info = $title_info[0];
//        dd($title_info);
        
        $usage_count = $title_info->usage_count;
        
        $update = Title::select('*')->where('id',$title_id)->update([
            "usage_count" => $usage_count + 1,
        ]);
        
        $create = MarketingPrice::create([
"title" => $request->title,
"travel_date" => $request->travel_date,
"time_departure" => $request->time_departure,
"total_price" => $request->total_price,
"cost_price" => $request->cost_price,
"booking_id" => $request->booking_id,
"screen_id" => $request->screen_id,
"added_by" => $request->added_by,
"places_available" => $request->places_available,
"hold_finish_time" => $request->hold_finish_time . " " . $request->hold_finish_time2 . ":00",
        ]);
        
         $save_log = Log::create([
            "log_txt" => "تم اضافة قائمة تسويق  $request->title",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        return redirect()->route('site.marketing_prices');
    }

    /**
     * Display the specified resource.
     */
    public function title_create()
    {
        return view('marketing_prices.title_create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function title_store(StoreTitleRequest $request)
    {
//        dd($request);
        
         if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('logo_files', $imageNewName, 'public');
        } else {
            $path = "no";
        }
        
        
           $save_log = Log::create([
            "log_txt" => "تم اضافة عنوان (بيان)  $request->title",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        $create = Title::create([
            "title" => $request->title,
           "png_icon" => $path, 
           "bk_color" => $request->bk_color, 
        ]);
        return redirect()->route('site.marketing_titles');
    }

    /**
     * Update the specified resource in storage.
     */
    public function delete(Request $request , $id)
    {
        
          $marketing_info = MarketingPrice::select('*')->where('id',$id)->get();
        abort_if(count($marketing_info) == 0 , 404);
        $marketing_info = $marketing_info[0];
//        dd($marketing_info);
        
        $title = $marketing_info->title;
        
        
        
          $title_info = Title::select('*')->where('id',$title)->get();
        abort_if(count($title_info) == 0 , 404);
        $title_info = $title_info[0];
//        dd($title_info);
        
        $usage_count = $title_info->usage_count;
        
        $update = Title::select('*')->where('id',$title)->update([
            "usage_count" => $usage_count - 1,
        ]);
        
         $save_log = Log::create([
            "log_txt" => "تم حذف قائمة تسويق  $id",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        $delete = MarketingPrice::select('*')->where('id',$id)->delete();
          return redirect()->route('site.marketing_prices_all');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function show($id)
    {
        $marketing_info = MarketingPrice::select('*')->where('id',$id)->get();
        abort_if(count($marketing_info) == 0 , 404);
        $marketing_info = $marketing_info[0];
        
         $titles = Title::select('*')->orderBy('id','DESC')->get();
        return view('marketing_prices.show' , ["titles"=>$titles , "marketing_info" => $marketing_info]);
    }
    public function update_save(UpdateMarketingPriceRequest $request){
//        dd($request)
        $id = (int) $request->id;
         $marketing_info = MarketingPrice::select('*')->where('id',$id)->get();
        abort_if(count($marketing_info) == 0 , 404);
        $marketing_info = $marketing_info[0];
        
        
        $update_marketing = MarketingPrice::select('*')->where('id',$id)->update([
"title" => $request->title,
"travel_date" => $request->travel_date,
"time_departure" => $request->time_departure,
"total_price" => $request->total_price,
"cost_price" => $request->cost_price,
"booking_id" => $request->booking_id,
"screen_id" => $request->screen_id,
"hold_finish_time" => $request->hold_finish_time,
        ]);
        
        
           $save_log = Log::create([
            "log_txt" => "تم تعديل بيانات قائمة تسويق  $request->title",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        
        return redirect()->route('site.marketing_prices_show' , $id);
    }
    public function titles(){
          $titles = Title::select('*')->orderBy('id','DESC')->get();
        return view('marketing_prices.titles' , ["titles"=>$titles]);
        
    }
    public function titles_remove(Request $request , $id){
        $title_info = Title::select('*')->where('id',$id)->get();
        abort_if(count($title_info) == 0 , 404);
        $title_info = $title_info[0];
        
        
        $title_remove = Title::select('*')->where('id',$id)->delete();
        
        
          $save_log = Log::create([
            "log_txt" => "تم حذف العنوان (البيان)  $title_info->title",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        return redirect()->route('site.marketing_titles');
    }
    public function titles_edit($id){
         $title_info = Title::select('*')->where('id',$id)->get();
        abort_if(count($title_info) == 0 , 404);
        $title_info = $title_info[0];
            $titles = Title::select('*')->orderBy('id','DESC')->get();
        
        
        return view('marketing_prices.edit_title' , ["titles" => $titles, "title_info" => $title_info]);
    }
    public function save_update(UpdateTitleRequest $request){
       $id = $request->id;
        
           $title_info = Title::select('*')->where('id',$id)->get();
        abort_if(count($title_info) == 0 , 404);
        $title_info = $title_info[0];
        
             if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('logo_files', $imageNewName, 'public');
        } else {
            $path = $title_info->png_icon;
        }
        
        
        
        $update = Title::select('*')->where('id',$id)->update([
            
"title"=>$request->title,
"png_icon"=>$path,
"bk_color"=>$request->bk_color,
        ]);
        
        
         $save_log = Log::create([
            "log_txt" => "تم تعديل بيانات العنوان (البيان)  $request->title",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        return redirect()->route('site.marketing_titles');
        
    }
}
