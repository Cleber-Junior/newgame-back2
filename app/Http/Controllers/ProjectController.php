<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Rewards;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller{

    protected Project $project;

    public function __construct(Project $project){
        $this->project = $project;
    }

    public function index(){
        $project = $this->project->all();
        if(!$project === null){
            return response()->json(['error' => 'Nenhum projeto encontrado'], 404);
        }

        $newData = $project->map(function($item){
            if($item->image){
                $item->image = Storage::disk('s3')->temporaryUrl($item->image, now()->addMinutes(10));
            }
            return $item;
        });

        return response()->json(['data' => $newData], 200);
    }

    public function show($id){
        $project = $this->project->find($id);
        if(!$project){
            return response()->json(['error' => 'Projeto não encontrado'], 404);
        }

        if($project->image){
            $url = Storage::disk('s3')->temporaryUrl($project->image, now());
            return response()->json(['msg' => 'Projeto encontrado', 'project' => $project, 'image' => $url], 200);

        }

        return response()->json(['msg' => 'Projeto encontrado', 'project' => $project], 200);

    }

    public function store(Request $request){
        $request['current_value'] = 0;
        $request['status'] = false;

        $request->validate($this->project->rules(), $this->project->feedback());

        $project = $this->project->create($request->all());

        return response()->json(['msg' => 'Projeto criado com sucesso', 'project' => $project], 201);
    }

    public function allByUser($id){
        $data = $this->project->where('id_creator', $id)->get();
        if($data === null){
            return response()->json(['error' => 'Nenhum projeto encontrado'], 404);
        }

        $url = $data->map(function($item){
            if($item->image){
                $item = Storage::disk('s3')->temporaryUrl($item->image, now()->addMinutes(5));
            } else {
                $item = null;
            }
            return $item;
        });

        return response()->json(['projects' => $data, 'url' => $url], 200);
    }

    public function findById($id){
        $project = $this->project->where('id', $id)->get();
        $newData = $project;

        if($newData[0]->image){
            $newData[0]->image = Storage::disk('s3')->temporaryUrl($newData[0]->image, now()->addMinutes(5));
        }

        $projectRewards = Rewards::where('id_project', $newData[0]->id)->get();

        return response()->json(['project' => $newData, 'rewards' => $projectRewards], 200);

    }

    public function update(Request $request, $id){
        $project = $this->project->find($id);
        if(!$project){
            return response()->json(['error' => 'Projeto não encontrado. Impossivel executar a atualização'], 404);
        }

        if($request->method() === 'PATCH'){
            $dinamicRules = array();
            foreach($project->rules() as $input => $rule){
                if(array_key_exists($input, $request->all())){
                    $dinamicRules[$input] = $rule;
                }
            }
            $request->validate($dinamicRules, $project->feedback());
        } else {
            $request->validate($project->rules(), $project->feedback());
        }

        if($request->file('image')){
            $image = $request->file('image');
            $path = $image->store('images', 's3');
            $project->image = $path;
            if($project->image){
                $project->update(['image' => $project->image]);
                $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5));
                return response()->json(['msg' => 'Imagem atualizada com sucesso', 'project' => $project, 'url' => $url], 200);
            }
        }

        $project->update($request->all());
        return response()->json(['msg' => 'Projeto atualizado com sucesso', 'project' => $project], 200);
    }

    public function finishProject(Request $request, $id){
        $project = $this->project->find($id);
        if(!$project){
            return response()->json(['error' => 'Projeto não encontrado. Impossivel executar a finalização'], 404);
        }

        $project->update($request->all());
        return response()->json(['msg' => 'Projeto criado com sucesso', 'project' => $project], 201);
    }

    public function generateLinkImage($id){
        $project = $this->project->find($id);
        $hash = $project->image;
        if(!$project){
            return response()->json(['error' => 'Projeto não encontrado. Impossivel gerar o link da imagem'], 404);
        }
        if(!$hash){
            return response()->json(['error' => 'Projeto não possui imagem'], 404);
        } else {
            $url = Storage::disk('s3')->temporaryUrl($hash, now()->addMinutes(10));
            return response()->json(['msg' => 'Imagem salva com sucesso', 'url' => $url], 200);
        }
    }

    public function destroy($id){
        $project = $this->project->find($id);
        if(!$project){
            return response()->json(['error' => 'Projeto não encontrado. Impossivel executar a exclusão'], 404);
        }

        $project->delete();
        return response()->json(['msg' => 'Projeto excluído com sucesso'], 200);
    }
}
