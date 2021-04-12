<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\OfferPost;
use App\Models\PasabuyUser;
use App\Models\RequestPost;
use App\Models\share;
use App\Models\User;
use App\Notifications\SharedNotification;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{

	/**
	 *    [create_offer_post save shopping offer post on database]
	 *    @author Al Vincent Musa
	 *    @param  Request $request [http request]
	 *    @return Illumiinate\Http\Response           [json response]
	 */
	public function create_offer_post(Request $request) {

		// validate data
		$request->validate([
            'email' => ['required', 'email', 'max:50'],
            'postIdentity' => ['required', 'max:100'],
            'postStatus' => ['required', 'string', 'max:50'],
            'deliveryArea' => ['required', 'max:500'],
            'shoppingPlace' => ['required', 'max:2000'],
            'deliverySchedule' => ['required', 'date'],
            'transportMode' => ['required', 'max:200'],
            'capacity' => ['required', 'max:100'],
            'paymentMethod' => ['required', 'max:200'],
            'caption' => ['required', 'max:200'],
		]);

		$user = Auth::User();

		$post = new Post;
		$post->email = $user->email;
		$post->postIdentity = $request->postIdentity;
		$post->postStatus = $request->postStatus;

		$offer_post = new OfferPost;
		$offer_post->postStatus = $request->postStatus;
		$offer_post->deliveryArea = $request->deliveryArea;
		$offer_post->shoppingPlace = $request->shoppingPlace;
		$offer_post->deliverySchedule = $request->deliverySchedule;
		$offer_post->transportMode = $request->transportMode;
		$offer_post->capacity = $request->capacity;
		$offer_post->paymentMethod = $request->paymentMethod;
		$offer_post->caption = $request->caption;

		// save to database
		DB::transaction(function() use ($post, $offer_post) {

			$post->save();
			$post->offer_post()->save($offer_post);
		});

		return response()->json(['message' => 'Offer post created successfully.'],201);
	}

	/**
	 *    [create_request_post save request post on database]
	 *    @author Al Vincent Musa
	 *    @param  Request $request [description]
	 *    @return [type]           [description]
	 */
	public function create_request_post(Request $request) {

		// validate data
		$request->validate([
			'email' => 'required|email',
			'postIdentity' => 'required|string|max:100',
			'postStatus' => 'required|string|max:50',
			'deliveryAddress' => 'required|string|max:500',
			'shoppingPlace' => 'required|string|max:500',
			'deliverySchedule' => 'required|date',
			'paymentMethod' => 'required|string|max:200',
			'shoppingList' => 'required|string|max:2000',
			'caption' => 'required|string|max:200',
		]);

		$user = Auth::User()->email;

		// post model
		$post = new Post;
		$post->email = $request->email;
		$post->postIdentity = $request->postIdentity;
		$post->postStatus = $request->postStatus;

		// request post model
		$request_post = new RequestPost;
		$request_post->postStatus = $request->postStatus;
		$request_post->deliveryAddress = $request->deliveryAddress;
		$request_post->shoppingPlace = $request->shoppingPlace;
		$request_post->deliverySchedule = $request->deliverySchedule;
		$request_post->paymentMethod = $request->paymentMethod;
		$request_post->shoppingList = $request->shoppingList;
		$request_post->caption = $request->caption;

			// save to database
		DB::transaction(function() use ($post, $request_post) {
			$post->save();
			$post->request_post()->save($request_post);
		});
		
		return response()->json([
				'message' => 'Request post created successfully.'
			],
			201
		);
	}

	/**
	 *    [get_user_posts description]
	 *    @author Al Vincent Musa
	 *    @param  Request $request [description]
	 *    @param  [type]  $userId  [description]
	 *    @return [type]           [description]
	 */
	public function get_user_posts(Request $request) {

		$user = Auth::user();
		$data = DB::select("SELECT * FROM tbl_post post, tbl_shoppingOfferPost offer_post, tbl_orderRequestPost request_post WHERE post.postNumber = offer_post.postNumber OR post.postNumber = request_post.postNumber AND post.postDeleteStatus = 0 AND post.email = ?", [$user->email]);

		return response()->json([
				$data
			],
			200
		);
	}

	public function getAllPosts(Request $request) {

		$user = Auth::user();
		// $data = PasabuyUser::has('post')->with('post','post.offer_post','post.request_post')->get();
		$data = Post::with('offer_post','request_post')->where('tbl_post.postDeleteStatus','=',0)->orderBy('tbl_post.dateCreated','desc')->get();

		foreach ($data as $convertingImage){ 
			
			$convertingImage->user->profilePicture = utf8_encode($convertingImage->user->profilePicture);
		}

		return response()->json($data);
	}

	public function getAllShares(Request $request)
	{
		# code...
		$data = share::with('post','post.offer_post','post.request_post','user')->orderBy('dateCreated','desc')->get();
		
		foreach ($data as $convertingImage){ 
			
			$convertingImage->user->profilePicture = utf8_encode($convertingImage->user->profilePicture);
		}

		return response()->json($data);
	}
	
	public function sharePost(Request $request)
	{
		# code...
		$user = Auth::user();
		$postNum = $request->postNum;

		$newShare = new share;
		$newShare->sharerEmail = $user->email;
		$newShare->shareNumber = share::count()+1;
		$newShare->postNumber = $postNum;
		if($newShare->save()){
			//find the right user to notify, in this case the owner of the post
			$userToNotif = Post::where('postNumber',$postNum)->get();
			if($userToNotif[0]->email == $user->email){
				return response()->json(["Success"],200);
			}
			$userToNotif = User::where('email',$userToNotif[0]->email)->get();
			$userToNotif = User::find($userToNotif[0]->indexUserAuthentication);
			$userToNotif->notify(new SharedNotification($postNum));
			return response()->json(['message'=>'You have successfully shared this post'],200);
		}else{
			return response()->json(["Error"=>"An error occured"],500);
		}

		
	}
    
}
