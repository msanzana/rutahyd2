<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
final class AuthController extends Controller
{
    public function __construct(private UserRepositoryInterface $users) {}
    public function login(Request $request)
    {
        $data=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $user=$this->users->firstWhere('email',$data['email']);
        if (!$user || !$user->active || !Hash::check($data['password'],$user->password_hash)) {
            throw ValidationException::withMessages(['email'=>'Credenciales incorrectas']);
        }
        return response()->json(['token'=>$user->createToken('rutahyd2')->plainTextToken,
            'user'=>['id'=>$user->id,'name'=>$user->name,'role'=>$user->role?->code]]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }
}