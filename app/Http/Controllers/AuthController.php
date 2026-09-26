<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Notif;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);
        $user = DB::transaction(function () use ($data) {
            $u = User::create([...$data, 'email' => strtolower($data['email']), 'role' => 'user']);
            // Les invitations reçues avant l'inscription sont rattachées au nouveau compte
            foreach (Invitation::with('board')->where('email', $u->email)->get() as $inv) {
                $inv->board->membres()->syncWithoutDetaching([$u->id => ['role' => 'membre']]);
                Notif::create(['user_id' => $u->id, 'board_id' => $inv->board_id, 'type' => 'invitation', 'texte' => "Vous avez rejoint le tableau « {$inv->board->nom} »."]);
                $inv->board->log($u, 'a rejoint le tableau');
                $inv->delete();
            }

            return $u;
        });

        return response()->json(['user' => $user, 'token' => $user->createToken('spa')->plainTextToken], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['E-mail ou mot de passe incorrect.']]);
        }

        return response()->json(['user' => $user, 'token' => $user->createToken('spa')->plainTextToken]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
