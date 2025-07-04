<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Models\Project;
use App\Models\Rewards;
use GuzzleHttp\Psr7\Request as HTTPRequest;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class PaymentController extends Controller {

    protected Payment $payment;
    protected User $user;
    protected Project $project;

    public function __construct(Payment $payment){
        $this->payment = $payment;
    }

    public function createReference(Request $request){
        $project = Project::find($request->project_id);

        $user = User::find($request->user);

        $reward = Rewards::find($request->reward_id);

        $localPayment = $this->payment->create([
            'value' => $reward['value'],
            'id_project' => $project['id'],
            'id_user' => $user['id'],
            'id_reward' => $reward['id'],
            'status' => 2,
            'date' => now(),
        ]);

        $client = new Client();

        $requestBody = [
            "items" =>  [
                [
                  "id" => $reward["id"],
                  "title" => $reward["name"],
                  "description" => $reward["description"],
                  "quantity" => 1,
                  "unit_price" => (float) $reward["value"],
                ]
            ],
            "external_reference" => "payment_" . $localPayment->id,
                "payer" => [
                  "name" => $user["fullname"],
                  "email" => $user["email"],
                    "identification" => [
                      "type" => "CPF",
                      "number" => $user["cpf"]
                  ],
                    "address" => [
                      "zip_code" => $user["zip_code"],
                      "street_name" => $user["street"],
                      "street_number" => $user["number"],
                    ],
                ],
                "shipments" => [
                   "local_pickup" => false,
                   "default_shipping_method" => null,
                    "receiver_address" => [
                      "zip_code" => $user["zip_code"],
                      "street_name" => $user["street"],
                      "city_name" => $user["city"],
                      "state_name" => $user["state"],
                      "street_number" => $user["number"],
                      "country_name" => "Brazil",
                    ],
                    ],
                "payment_methods" => [
                    "excluded_payment_types" => [
                        ["id" => "ticket"],
                        ["id" => "bank_transfer"]
                    ],
                    "installments" => 6,
                ],
                "back_urls" => [
                  "success" => "https://www.new-game.shop/user/supported",
                  "pending" => "https://www.new-game.shop/user/supported",
                  "failure" => "https://www.new-game.shop/user/supported"
                ],
                "notification_url" => "https://newgame-da3be6e96e82.herokuapp.com/api/webhook/mercadopago",
        ];

        $request = new HTTPRequest('POST', 'https://api.mercadopago.com/checkout/preferences', [
            'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
            'Content-Type' => 'application/json'
        ], json_encode($requestBody));

        $res = $client->sendAsync($request)->wait();
        $responseBody = json_decode($res->getBody(), true);

        $preferenceId = $responseBody['id'];

        $localPayment->id_preference = $preferenceId;
        $localPayment->save();

        $linkToPay = $responseBody['sandbox_init_point'];

        return response()->json(['msg' => 'Referência criada com sucesso', 'reference' => $linkToPay], 201);
    }

    public function storePayment(array $data){
        $this->payment->create($data);
    }

    public function getPayments(User $user){
        $payments = Payment::where('id_user', $user['id'])->get(); // Pego os pagamentos de acordo com o ID do Usuário

        $projectsIds = $payments->pluck('id_project'); // Pego os IDs dos projetos

        $projects = Project::whereIn('id', $projectsIds)->get(); // Pega os projetos que tenham os Ids correspondestes

        $responseBody = [];
        foreach($payments as $payment){
            $project = $projects->firstWhere('id', $payment->id_project);

            if($project){
                $responseBody[] = [
                    'project_name' => $project->name,
                    'current_value' => $project->current_value,
                    'meta_value' => $project->meta_value,
                    'value' => $payment->value,
                    'end_date' => $project->end_date,
                    'payment_date' => $payment->date,
                    'status' => $payment->status,
                ];
            };
        };

        return response()->json(['supported' => $responseBody], 200);
    }
}
