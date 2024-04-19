<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Team;
use App\Models\User;
use App\Events\UserInvitedToTeam;
use App\Models\UserTeamRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Teams\TeamResource;

class TeamController extends Controller
{

    public function index()
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not logged in.'
            ], 401);
        }

        // $teams = Team::all();
        // $teams->makeHidden('creator_id');

        // new TeamResource($team)

        return response()->json([
            'status' => 'success',
            'data' => TeamResource::collection(Team::all())
        ], 200);
    }

    public function userTeams()
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not logged in.'
            ], 401);
        }
    
        $user = Auth::user();
    
        $teams = $user->teams;
    
        return response()->json([
            'status' => 'success',
            'data' => $teams
        ], 200);
    }
    

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'size' => 'required|integer|max:5',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'Error: see errors',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $imageName = 'no_image_available.jpg';
    
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'.'.$image->getClientOriginalExtension();
            $image->move(public_path('images'), $imageName);
        }
    
    
        $team = new Team();
        $team->name = $request->input('name');
        $team->size = $request->input('size');
        $team->image = $imageName;
        $team->creator_id = Auth::id();
        $team->save();
    
        
        $team->users()->attach(Auth::id());
    
        return response()->json([
            'status' => 'success',
            'data' => new TeamResource($team)
        ], 200);
    }

    public function show($id)
    {
        $team = Team::find($id);
    
        if ($team === null) {
            $statusMsg = 'Team not found!';
            $statusCode = 404;
        } else {
            $statusMsg = 'success';
            $statusCode = 200;
            $team->makeHidden('creator_id');
            $team = new TeamResource($team);
        }
    
        return response()->json([
            'status' => $statusMsg,
            'data' => $team
        ], $statusCode);
    }

    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);

        if ($team === null) {
            return response()->json([
                'status' => 'Team not found!',
                'data' => null
            ], 404);
        }

        if (Auth::user()->roles->contains('name', 'admin')) {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
                'imageFile' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                'size' => 'required|integer|max:5',
                'wins' => 'integer',
                'losses' => 'integer'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'Error: see below',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            
            if ($request->hasFile('imageFile')) {
                $image = $request->file('imageFile');
                $imageName = time().'.'.$image->getClientOriginalExtension();
                $image->move(public_path('images'), $imageName);
            } else {
                $imageName = $team->imageFormal;
            }
    
            $team->name = $request->input('name');
            $team->size = $request->input('size');
            $team->wins = $request->input('wins');
            $team->image = $imageName;
            $team->losses = $request->input('losses');
            $team->save();
            return response()->json([
                'status' => 'success',
                'data' => new TeamResource($team)
            ], 200);
        } else {
            if ($team->creator_id !== Auth::id()) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'You are not authorized to perform this action.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                'size' => 'required|integer|max:5',
            ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'Error: see below',
                'errors' => $validator->errors()
            ], 422);
        }

        


        if ($request->hasFile('imageFile')) {
            $image = $request->file('imageFile');
            $imageName = time().'.'.$image->getClientOriginalExtension();
                        // delete file with name $team->imageFormal
            $image->move(public_path('images'), $imageName);
            $team->image = $imageName;
        } 
        // else {
        //     $imageName = $team->imageFormal;
        // }

        $team->name = $request->input('name');
        $team->size = $request->input('size');
        $team->save();
        return response()->json([
            'status' => 'success',
            'data' => new TeamResource($team)
        ], 200);
    }
}

    public function destroy(Request $request, $id)
    {
        $team = Team::findOrFail($id);

        if ($team === null) {
            return response()->json([
                'status' => 'Team not found!',
                'data' => null
            ], 404);
        }

        if (Auth::user()->roles->contains('name', 'admin')) {
            $team->users()->detach();
            $team->delete();
            return response()->json([
                'status' => 'success',
                'message' => "Team: {$id} deleted"
            ], 200);
        }

        if ($team->creator_id !== Auth::id()) {
            return response()->json([
                'status' => 'Error',
                'message' => 'You are not authorized to perform this action.'
            ], 403);
        }

        if ($team->users()->count() > 0) {
            $team->users()->detach();
        }

        $team->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Team: {$id} deleted"
        ], 200);
    }

    public function joinTeam(Request $request, $id)
    {
        $userId = Auth::id();
        $team = Team::find($id);

        if ($team === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found.'
            ], 404);
        }

        if ($team->size !== null && $team->users->count() >= $team->size) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team size limit reached. Cannot join.'
            ], 400);
        }

        if (!$team->users->contains($userId)) {
            $team->users()->attach($userId);
            return response()->json([
                'status' => 'success',
                'message' => 'User joined the team successfully.'
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already a member of the team.'
            ], 400);
        }
    }

    public function leaveTeam($id)
    {
        $userId = Auth::id();
        $team = Team::find($id);

        if ($team === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found.'
            ], 404);
        }

        if ($team->users->contains($userId)) {
            $team->users()->detach($userId);
            if ($team->users()->count() === 0) {
                $team->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'User removed from the team successfully. The team has been deleted since there are no more users.'
                ], 200);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'User left the team successfully.'
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not a member of the team.'
            ], 400);
        }
    }

    public function removeUser(Request $request, $id, $userId)
    {
        $user = Auth::user();
        $team = Team::find($id);

        if ($team === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found.'
            ], 404);
        }

        if ($user->roles->contains('name', 'admin') || $team->creator_id === $user->id) {
            if ($team->users->contains($userId)) {
                $team->users()->detach($userId);
                if ($team->users()->count() === 0) {
                    $team->delete();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'User removed from the team successfully. The team has been deleted since there are no more users.'
                    ], 200);
                }
                return response()->json([
                    'status' => 'success',
                    'message' => 'User removed from the team successfully.'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is not a member of the team.'
                ], 400);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.'
            ], 403);
        }
    }

    public function inviteUser(Request $request, $teamId, User $user)
    {
        $creatorId = Auth::id();
        $team = Team::find($teamId);
    
        if ($team === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found.'
            ], 404);
        }
    
        $input = $request->all();
        $username = $input['username'] ?? null;
    
        if (!$username) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please provide username.'
            ], 400);
        }
    
        $user = null;
    
    
        if ($username) {
            $user = User::where('username', $username)->first();
        }
    
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }
    
        if ($team->size !== null && $team->users->count() >= $team->size) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team size limit reached. Cannot invite more users.'
            ], 400);
        }
    
        if ($team->creator_id !== $creatorId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the captain of the team can invite users.'
            ], 403);
        }
    
        if ($team->users->contains($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already a member of the team.'
            ], 400);
        }
    
        // $team->users()->attach($user->id, ['status' => 'pending', 'created_at' => now()]);
        // $team->users()->attach($user->id);
        event(new UserInvitedToTeam($team, $user));
        return response()->json([
            'status' => 'success',
            'message' => 'User invited to the team successfully.'
        ], 200);
    }

    //easier to access the teams here rather then the other controller
    public function acceptInvite(Request $request, $teamId)
    {
        try {
            $user = $request->user();
            
            // Retrieve the Team by its ID
            $team = Team::findOrFail($teamId);
            
            //all associated team invites with this user
            $invite = UserTeamRequest::where('user_id', $user->id)
                ->where('team_id', $team->id)
                ->first();
            
            // If an invite exists, attached the user to the team
            if ($invite) {
                $team->users()->attach($user->id);
                
                // Delete the invite record to maintain database
                $invite->delete();
                
                return response()->json(['message' => 'Invite accepted and joined the team successfully.'], 200);
            } else {
                return response()->json(['message' => 'No pending invitation found for this team.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to accept the invite and join the team.'], 500);
        }
    }
    
}