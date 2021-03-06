<?php
namespace App\Http\Controllers;

use App\Article;
use App\Attachfile;
use App\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;

class ArticleController extends Controller {
    
    // 存储图片
    public function saveUploadImg(Request $request) {
        // $file from Symfony\Component\HttpFoundation\File 
        $file = $request['img'];
        if($file->isValid()) {
            $loginUser = Auth::user();
            $file_dir = $loginUser->account;
            $file_name = date('YmdHis').'.'.$file->getClientOriginalExtension();
            $path = $request->file('img')->storeAs($file_dir, $file_name, 'images');
            $url = Storage::disk('images')->url($path);
            return response()->json(['url' => $url]);
        }else {
            return response()->json(['status' => 418, 'msg' => 'File invalid']);
        }
    }

    public function saveWriter(Request $request) {
        $loginUser = Auth::user();
        if (intval($request->user_id) !== $loginUser->id) {
            return response()->json(['status' => 402, 'msg' => 'auth error']);
        }
        
        $result = Article::create([
            'title' => $request->title,
            'content' => $request->content,
            // 'content' => $request->htmlcontent,
            'attachfiles_id' => $request->attachfiles_id,
            // 'visit_count' => $request->visit_count,
            // 'like_count' => $request->like_count,
            // 'hot' => $request->hot,
            'category_id' => intval($request->category_id),
            'user_id' => intval($request->user_id),
            'updated_user' => $loginUser->name
        ]);
        $article_id = $result->id;
        $share_user_id = $request->share_user_id;
        $insert_arr = array(array('article_id'=>$article_id, 'user_id'=>intval($request->user_id)));
        foreach ($share_user_id as $value) {
            array_push($insert_arr, array('article_id'=>$article_id, 'user_id'=>$value));
        }
        if (DB::table('article_user')->insert($insert_arr)) {
            return response()->json(['status' => 200, 'msg' => 'success']);
        } else {
            return response()->json(['status' => 402, 'msg' => 'error']);
        }
    }

    // 更新文章详细信息
    public function updateDetail(Request $request) {
        $loginUser = Auth::user();
        // 判断当前用户有没有文章的权限
        if (!$this->articleAuth(intval($request->id))) {
            return response()->json(['status' => 402, 'msg' => 'auth error']);
        }
        $result = Article::where([ ['id', intval($request->id)], ['user_id', intval($request->user_id)] ])->update([
            'title' => $request->title,
            'content' => $request->content,
            // 'content' => $request->htmlcontent,
            'attachfiles_id' => json_encode($request->attachfiles_id),
            'category_id' => intval($request->category_id),
            'updated_user' => $loginUser->name
        ]);
        if ($result) {

            $article_id = intval($request->id);
            $share_user_id = $request->share_user_id;
            array_push($share_user_id, $loginUser->id);

            // 获取当前数据库中的共同编辑用户
            $current_share_user_id = DB::table('article_user')->where('article_id', $article_id)->pluck('user_id')->toArray();
            // dd(, array_diff($share_user_id, $current_share_user_id));
            // 删除被去除的用户
            $delete_user_id = array_diff($current_share_user_id, $share_user_id);
            DB::table('article_user')->where('article_id', $article_id)->whereIn('user_id', $delete_user_id)->delete();

            // 添加新增的用户
            $insert_arr = array();
            $add_user_id = array_diff($share_user_id, $current_share_user_id);
            foreach ($add_user_id as $value) {
                array_push($insert_arr, array('article_id'=>$article_id, 'user_id'=>$value));
            }
            if (DB::table('article_user')->insert($insert_arr)) {
                return response()->json(['status' => 200, 'msg' => 'success']);
            } else {
                return response()->json(['status' => 402, 'msg' => 'error']);
            }
        }
    }

    // 获取待编辑的文章详细信息
    public function getEditDetail(Request $request) {
        $loginUser = Auth::user();
        $article_id = intval($request->id);
        // 判断当前用户有没有文章的权限
        if (!$this->articleAuth($article_id)) {
            return response()->json(['status' => 402, 'msg' => 'auth error']);
        }
        $articleDetail = Article::find($article_id);
        
        // 获取共同编辑的用户id 除登录用户之外
        $shareUserId = DB::table('article_user')->where([ ['article_id', $article_id], ['user_id', '!=', $loginUser->id] ])->pluck('user_id')->toArray();

        if($articleDetail->attachfiles_id != 'null' && $articleDetail->attachfiles_id != '') {
            $attachfiles = Attachfile::select('id', 'f_name as name', 'f_path')->whereIn('id', $articleDetail->attachfiles_id)->get();
        }else {
            $attachfiles = array();
        }
        return array('articleDetail' => $articleDetail, 'attachfiles' => $attachfiles, 'shareUserId' => $shareUserId);
    }

    // 获取所有文章
    public function getArticle(Request $request) {
        // 每页条数
        $size = intval($request->size);
        // 页数
        $page = intval($request->page);

        // DB::enableQueryLog();
        $articles = Article::with('username')->orderBy('updated_at', 'desc')->offset( ($page-1)*$size )->limit($size)->get()->toArray();
        $articles = array_map(
                        function($item) { 
                            $item['username'] = $item['username']['name'];
                            return $item;
                        }, 
                        $articles
                    );
        $total = Article::count();
        $result = array('articles' => $articles, 'total' => $total );
        return $result;
    }

    // 根据登录用户获取文章列表 包括可以共同编辑的文章
    public function getArticleByUser(Request $request) {
        $loginUser = Auth::user();
        if( $loginUser->role == 1 ){
            // 每页条数
            $size = intval($request->size);
            // 页数
            $page = intval($request->page);

            // 获取当前用户是否有共享编辑的文档
            $user_info = array();
            $articleIdArr = DB::table('article_user')->where('user_id', $loginUser->id)->orderBy('article_id')->pluck('article_id')->toArray();
            // dd(DB::getQueryLog());
            // 获取共同编辑文档的所有用户名
            foreach ($articleIdArr as $articleId) {
                $result = DB::table('article_user')->select('users.id as userid', 'users.name as username')->join('users', 'users.id', '=', 'article_user.user_id')->where('article_id', $articleId)->orderBy('article_id')->get()->toArray();
                $user_info[$articleId] = array_map('get_object_vars', $result);
            }
            // DB::enableQueryLog();
            $articles = Article::with('username')->where('user_id', $loginUser->id)->orWhereIn('id', $articleIdArr)->orderBy('updated_at', 'desc')->offset( ($page-1)*$size )->limit($size)->get()->toArray();
            // dd(DB::getQueryLog());
            foreach ($articles as $key => $value) {
                $articles[$key]['username'] = $value['username']['name'];
                if (!empty($user_info[$value['id']])) {
                    $articles[$key]['share'] = implode(' | ', array_column($user_info[$value['id']], 'username'));
                } else {
                    $articles[$key]['share'] = '无';
                }
            }
            $total = DB::table('article_user')->where('user_id', $loginUser->id)->count();
            $result = array('articles' => $articles, 'total' => $total );
            return $result;
        }else {
            return response()->json(['status'=>400 , 'msg'=>'权限错误']);
        }
    }

    // 根据不同用户获取文章列表
    public function getArticlesByHero(Request $request) {
        $user_id = $request->input('user_id');

        $articles = Article::with('username')->where('user_id', $user_id)->orderBy('updated_at', 'desc')->get()->toArray();
        $articles = array_map(
                        function($item) { 
                            $item['username'] = $item['username']['name'];
                            return $item;
                        }, 
                        $articles
                    );
        return $articles;
    }

    public function getByCategory(Request $request) {
        $category_id = intval($request->id);
        $articles = Article::with('username')->where('category_id', $category_id)->orderBy('updated_at', 'desc')->get()->toArray();
        $articles = array_map(
                        function($item) { 
                            $item['username'] = $item['username']['name'];
                            return $item;
                        }, 
                        $articles
                    );
        return $articles;
    }

    // 获取详细信息
    public function getArticleDetail(Request $request) {
        $id = intval($request->id);
        // 阅读量 +1
        $this->visitCountUp($id);

        $articles = Article::with('username')->find($id)->toArray();
        $articles['username'] = $articles['username']['name'];
        if($articles['attachfiles_id'] != 'null' && $articles['attachfiles_id'] != '') {
            $attachfiles = Attachfile::select('id', 'f_name')->whereIn('id', $articles['attachfiles_id'])->get();    
        }else {
            $attachfiles = array();
        }
        return array('articles' => $articles, 'attachfiles' => $attachfiles);
    }

    public function getOrderArticleList() {
        $order_by_visit = Article::orderBy('visit_count', 'desc')->limit(5)->get();
        $order_by_like = Article::orderBy('like_count', 'desc')->limit(5)->get();
        $orderList = array('order_by_visit' => $order_by_visit , 'order_by_like' => $order_by_like);
        return $orderList;
    }
    // 获取根据文章发布数量排序的用户列表
    public function getHeroList() {
        $article_total_raw = DB::raw('count(*) as total');
        $list = Article::with('username')->select('user_id', $article_total_raw)->groupBy('user_id')->orderBy('total', 'desc')->limit(50)->get()->toArray();
        foreach ($list as $key => $value) {
            $list[$key]['username'] = $value['username']['name'];
        }
        return response()->json($list);
    }

    public function visitCountUp($id) {
        $article = Article::find($id);
        $article->timestamps = false;
        $article->visit_count += 1;
        $article->save();
        // return Article::orderBy('visit_count', 'desc')->limit('5')->get()->toJson();
        // return Article::all()->toJson();
    }

    public function delete(Request $request) {
        $loginUser = Auth::user();
        $article_id = intval($request->id);
        if( $loginUser->role == 0 ){
            $user_id = intval($request->user_id);
            $deletedRows = Article::where([ ['id', $article_id], ['user_id', $user_id] ])->delete();
            if($deletedRows) {
                return $this->getArticle($request);
            }
        }elseif( $loginUser->role == 1 ) {
            $user_id = $loginUser->id;
            $deletedRows = DB::table('article_user')->where('article_id', $article_id)->delete();
            if($deletedRows) {
                $deletedRows = Article::where([ ['id', $article_id], ['user_id', $user_id] ])->delete();
                if ($deletedRows) {
                    return $this->getArticleByUser($request);
                }
                
            }
        }else {
            return response()->json(['status' => 400, 'msg' => '权限错误']);
        }
    }

    // 搜索文章
    public function searchArticle(Request $request) {
        $keyword = $request->input('searchKeyword');

        // 每页条数
        $size = intval($request->size);
        // 页数
        $page = intval($request->page);

        $articles = Article::with('username')->where('title', 'like', '%'.$keyword.'%')->orderBy('updated_at', 'desc')->offset( ($page-1)*$size )->limit($size)->get()->toArray();
        $articles = array_map(
                        function($item) { 
                            $item['username'] = $item['username']['name'];
                            return $item;
                        }, 
                        $articles
                    );
        $total = Article::where('title', 'like', '%'.$keyword.'%')->count();
        $result = array('articles' => $articles, 'total' => $total );
        return $result;
    }

    // 判断当前用户是否拥有文章权限
    private function articleAuth($article_id) {
        $loginUser = Auth::user();
        $count = DB::table('article_user')->where([ ['article_id', $article_id], ['user_id', $loginUser->id] ])->count();
        if ($count <= 0) {
            // 如果没有访问权限
            return false;
        } else {
            return true;
        }
    }

    
}
?>