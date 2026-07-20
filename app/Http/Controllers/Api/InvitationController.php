<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PlatformInvitationMail;
use App\Models\SearchInvitation;
use App\Models\SystemInvitation;
use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\User;
use App\Models\Responder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * Listar Convites
     * @tags Convites
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->input('tab', 'sent');

        $searchInvites = DB::table('search_invitations')
            ->join('users as senders', 'search_invitations.sender_id', '=', 'senders.id')
            ->leftJoin('searches', 'search_invitations.search_id', '=', 'searches.id')
            ->select(
                'search_invitations.id',
                'search_invitations.email',
                'search_invitations.name as invitee_name',
                'search_invitations.status',
                'search_invitations.sender_id',
                'senders.name as sender_name',
                'searches.id as search_id',
                'searches.title as search_title',
                'searches.status as search_status',
                'search_invitations.created_at',
                DB::raw("IF(search_invitations.responder_id IS NULL, 'co_manager', 'search_responder') as type")
            );

        $systemInvites = DB::table('system_invitations')
            ->join('users as senders', 'system_invitations.sender_id', '=', 'senders.id')
            ->select(
                'system_invitations.id',
                'system_invitations.email',
                'system_invitations.name as invitee_name',
                'system_invitations.status',
                'system_invitations.sender_id',
                'senders.name as sender_name',
                DB::raw("NULL as search_id"),
                DB::raw("NULL as search_title"),
                DB::raw("NULL as search_status"),
                'system_invitations.created_at',
                DB::raw("'system_access' as type")
            );

        $query = DB::table(DB::raw("({$searchInvites->toSql()} UNION ALL {$systemInvites->toSql()}) as invites"))
            ->mergeBindings($searchInvites)->mergeBindings($systemInvites);

        if ($tab === 'all' && $user->type !== 'master') {
            return response()->json(['message' => 'Acesso negado à visão global.'], 403);
        } elseif ($tab === 'sent') {
            $query->where('sender_id', $user->id);
        } elseif ($tab === 'received') {
            $query->where('email', $user->email)
                ->whereIn('status', ['pending', 'sent', 'standby']);
        }

        if ($request->filled('inviter') && $tab === 'all')
            $query->where('sender_name', 'like', '%' . $request->inviter . '%');
        if ($request->filled('invitee'))
            $query->where(fn($q) => $q->where('email', 'like', '%' . $request->invitee . '%')->orWhere('invitee_name', 'like', '%' . $request->invitee . '%'));
        if ($request->filled('search_id'))
            $query->where('search_id', $request->search_id);
        if ($request->filled('type'))
            $query->where('type', $request->type);

        return response()->json($query->orderByDesc('created_at')->paginate(15));
    }

    /**
     * Reenviar Convite Manualmente
     * @tags Convites
     */
    public function resend($id, Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');
        $invitation = $type === 'system_access' ? SystemInvitation::findOrFail($id) : SearchInvitation::with('search')->findOrFail($id);

        if ($user->type !== 'master' && $invitation->sender_id !== $user->id)
            return response()->json(['message' => 'Permissão negada.'], 403);
        if (in_array($invitation->status, ['accepted', 'registered']))
            return response()->json(['message' => 'O convite já foi aceito pelo destinatário.'], 422);

        if ($type !== 'system_access' && in_array($invitation->search->status, ['draft', 'closed', 'archived'])) {
            return response()->json(['message' => 'Não é possível reenviar convites. A pesquisa não está publicada.'], 422);
        }

        try {
            if ($type === 'system_access') {
                $emailBody = "Olá!\n\nVocê recebeu um convite de acesso à Plataforma Científica AMB.\n\nAcesse: https://amb-pesquisas.org.br/";
                $subject = "Convite de Acesso - AMB Pesquisas";
            } else {
                $surveyUrl = "https://amb-pesquisas.org.br/responder/{$invitation->search->id}";
                $emailBody = "Olá, {$invitation->name}.\n\nReenviando o convite para a pesquisa científica: \"{$invitation->search->title}\".\n\nParticipe pelo link: " . $surveyUrl;
                $subject = "Lembrete: Convite Científico AMB - " . $invitation->search->title;
            }

            Mail::raw($emailBody, fn($msg) => $msg->to($invitation->email)->subject($subject));

            $newStatus = in_array($invitation->status, ['declined', 'failed']) ? 'sent' : $invitation->status;
            $invitation->update(['status' => $newStatus, 'delivery_status' => 'success_email']);

            return response()->json(['message' => 'O convite foi reenviado com sucesso.']);
        } catch (\Exception $e) {
            Log::error("Falha ao reenviar convite: " . $e->getMessage());
            $invitation->update(['delivery_status' => 'failed']);
            return response()->json(['message' => 'Falha técnica ao despachar o e-mail.'], 500);
        }
    }

    /**
     * Remover Convite
     * @tags Convites
     */
    public function destroy($id, Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        if ($type === 'system_access') {
            $invitation = SystemInvitation::findOrFail($id);
            if ($user->type !== 'master' && $invitation->sender_id !== $user->id)
                return response()->json(['message' => 'Permissão negada.'], 403);
            $invitation->delete();
            return response()->json(['message' => 'Convite institucional removido.']);
        }

        $invitation = SearchInvitation::with('search')->findOrFail($id);

        if ($user->type !== 'master' && $invitation->sender_id !== $user->id)
            return response()->json(['message' => 'Permissão negada.'], 403);
        if ($invitation->status === 'accepted')
            return response()->json(['message' => 'Ações bloqueadas: Não é possível remover um convite já aceito.'], 422);
        if ($invitation->search && $invitation->search->status !== 'draft')
            return response()->json(['message' => 'Ações bloqueadas: A pesquisa já foi publicada.'], 422);

        $invitation->delete();
        return response()->json(['message' => 'Convite removido com sucesso.']);
    }

    /**
     * Aceitar Convite (Co-Gestão)
     * @tags Convites
     */
    public function accept($id, Request $request): JsonResponse
    {
        $user = $request->user();
        $invitation = SearchInvitation::where(fn($q) => $q->where('responder_id', $user->id)->orWhere('email', $user->email))->findOrFail($id);

        if ($invitation->status === 'accepted')
            return response()->json(['message' => 'Este convite já foi aceito.'], 422);

        $invitation->update(['status' => 'accepted']);
        if ($invitation->responder_id === null) {
            $adminUser = User::where('email', $invitation->email)->firstOrFail();
            Search::findOrFail($invitation->search_id)->managers()->syncWithoutDetaching([$adminUser->id]);
        }

        return response()->json(['message' => 'Convite aceito com sucesso!']);
    }

    /**
     * Recusar Convite
     * @tags Convites
     */
    public function decline($id, Request $request): JsonResponse
    {
        $user = $request->user();
        $invitation = SearchInvitation::where(fn($q) => $q->where('responder_id', $user->id)->orWhere('email', $user->email))->findOrFail($id);

        if ($invitation->status === 'accepted')
            return response()->json(['message' => 'Convite já processado.'], 422);

        $invitation->update(['status' => 'declined']);
        return response()->json(['message' => 'Convite recusado com sucesso.']);
    }

    // =========================================================================
    // MÓDULO DE PÚBLICO-ALVO / INSERÇÃO DE DADOS (Rotas de Criação/Disparo)
    // =========================================================================

    /**
     * Vincular Profissionais Elegíveis (Acionado pelo Wizard)
     * @tags Público-Alvo
     */
    public function bindEligibleUsers(Request $request, $id): JsonResponse
    {
        $data = $request->validate(['users' => 'required|array|min:1', 'users.*' => 'integer|exists:responders,id']);

        $boundCount = $this->processInternalInvitations($request->user(), Search::findOrFail($id), $data['users']);

        return response()->json(['message' => 'Profissionais vinculados com sucesso.', 'bound_count' => $boundCount], 200);
    }

    /**
     * Enviar Convites Customizados em Lote (Acionado pela Central de Disparos)
     * @tags Público-Alvo
     */
    public function storeCustomBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'responder_ids' => 'required|array|min:1',
            'responder_ids.*' => 'exists:responders,id',
            'action_type' => 'required|in:invite_search,free_notification',
            'search_id' => 'required_if:action_type,invite_search|exists:searches,id',
            'message' => 'nullable|string',
        ]);

        if ($data['action_type'] === 'invite_search') {
            $this->processInternalInvitations($request->user(), Search::findOrFail($data['search_id']), $data['responder_ids'], $data['message']);
        }

        return response()->json(['message' => 'Lote customizado processado com sucesso.']);
    }

    /**
     * Importar usuários externos (Acionado pelo Wizard via Planilha)
     * @tags Público-Alvo
     */
    public function importTargets(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'nullable|string|min:10',
            'users' => 'required|array|min:1',
            'users.*.name' => 'required|string|max:255',
            'users.*.email' => 'required|email',
            'users.*.phone' => 'nullable|string',
            'users.*.crm' => 'nullable',
            'users.*.crm_state' => 'sometimes|nullable|string|size:2',
        ]);

        $rawUsers = $request->input('users');

        $result = $this->processExternalInvitations(
            $request->user(),
            Search::findOrFail($id),
            $rawUsers,
            $data['message'] ?? null
        );

        return response()->json(['message' => 'Usuários externos processados.', 'new_count' => $result['new_count']], 200);
    }

    /**
     * MOTOR 2: Processa convites de listas externas (Cria o médico se não existir e gerencia metadados).
     */
    private function processExternalInvitations(User $sender, Search $search, array $usersData, ?string $customMessage = null): array
    {
        $this->validateManagementPermission($sender, $search);
        $this->validateSearchIsOpen($search);

        $initialStatus = $search->status === 'published' ? 'sent' : 'pending_publish';
        $newCount = 0;
        $existingCount = 0;

        $nativeColumns = ['name', 'email', 'phone', 'crm', 'crm_state'];

        DB::transaction(function () use ($usersData, $sender, $search, $initialStatus, $nativeColumns, $customMessage, &$newCount, &$existingCount) {

            foreach ($usersData as $rowData) {
                $rowData = array_change_key_case($rowData, CASE_LOWER);

                if (empty($rowData['email'])) {
                    continue;
                }

                $email = trim(strtolower($rowData['email']));
                $responder = Responder::where('email', $email)->first();

                $metadata = [];
                foreach ($rowData as $key => $value) {
                    if (!in_array($key, $nativeColumns)) {
                        $metadata[$key] = $value;
                    }
                }

                if ($responder) {
                    // --- USUÁRIO JÁ EXISTE ---
                    $updatedMetadata = array_merge($responder->metadata ?? [], $metadata);
                    $responder->update([
                        'metadata' => !empty($updatedMetadata) ? $updatedMetadata : null
                    ]);

                    SearchInvitation::updateOrCreate(
                        ['search_id' => $search->id, 'email' => $email],
                        [
                            'sender_id' => $sender->id,
                            'name' => trim($rowData['name'] ?? $responder->name),
                            'phone' => $rowData['phone'] ?? $responder->phone ?? null,
                            'status' => $initialStatus,
                            'delivery_status' => 'standby',
                            'responder_id' => $responder->id
                        ]
                    );
                    $existingCount++;

                    if ($search->status === 'published') {
                        try {
                            $surveyUrl = env('APP_URL', 'https://amb-pesquisas.org.br') . "/responder/{$search->id}";
                            $msgPublished = $customMessage ?? "Olá, {$responder->name}. Você foi convidado para a pesquisa: \"{$search->title}\".\nAcesse: {$surveyUrl}";

                            Mail::raw($msgPublished, fn($m) => $m->to($email)->subject("Convite AMB - " . $search->title));
                            SearchInvitation::where('search_id', $search->id)->where('email', $email)->update(['delivery_status' => 'success_email']);
                        } catch (\Exception $e) {
                            Log::warning("Falha SMTP usuário existente: " . $e->getMessage());
                        }
                    }

                } else {
                    // --- USUÁRIO NÃO EXISTE (Novo Onboarding com Senha) ---

                    // 🌟 1. Gera a senha plana para enviar por e-mail
                    $plainPassword = Str::random(10);

                    $newResponder = Responder::create([
                        'name' => trim($rowData['name'] ?? 'Profissional'),
                        'email' => $email,
                        'phone' => $rowData['phone'] ?? null,
                        'crm' => $rowData['crm'] ?? null,
                        'crm_state' => $rowData['crm_state'] ?? null,
                        'active' => true,
                        'password' => Hash::make($plainPassword), // Salva o Hash no banco
                        'inviter_id' => $sender->id,
                        'metadata' => !empty($metadata) ? $metadata : null
                    ]);

                    SearchInvitation::create([
                        'search_id' => $search->id,
                        'sender_id' => $sender->id,
                        'email' => $email,
                        'name' => $newResponder->name,
                        'phone' => $newResponder->phone,
                        'status' => $initialStatus,
                        'delivery_status' => 'standby',
                        'responder_id' => $newResponder->id
                    ]);

                    SystemInvitation::updateOrCreate(
                        ['email' => $email], // Condição de busca (evita o erro 1062)
                        [
                            'sender_id' => $sender->id,
                            'name' => $newResponder->name,
                            'phone' => $newResponder->phone,
                            'status' => 'pending',
                            'metadata' => !empty($metadata) ? $metadata : null
                        ]
                    );

                    $newCount++;

                    // 🌟 2. Puxa a URL Base do .env e monta a mensagem com credenciais
                    $loginUrl = env('FRONTEND_URL', env('APP_URL', 'https://amb-pesquisas.org.br')) . '/login';

                    if ($search->status === 'published') {
                        $baseMsg = $customMessage ?? "Você foi convidado para responder a pesquisa: \"{$search->title}\" na base científica da AMB.";
                    } else {
                        $baseMsg = $customMessage ?? "Você está sendo convidado por {$sender->name} para participar da plataforma AMB-Pesquisas.";
                    }

                    // Concatena as credenciais geradas na mensagem enviada ao Mailable
                    $msgWithCredentials = $baseMsg . "\n\nPara acessar o sistema, utilize seus dados abaixo:\n\nE-mail de acesso: {$email}\nSenha Provisória: {$plainPassword}\n\n*Recomendamos alterar sua senha no menu Perfil logo após o seu primeiro acesso.";

                    try {
                        Mail::to($email)->send(new PlatformInvitationMail($msgWithCredentials, $loginUrl));

                        if ($search->status === 'published') {
                            SearchInvitation::where('search_id', $search->id)->where('email', $email)->update(['delivery_status' => 'success_email']);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Falha SMTP Novo Usuário: " . $e->getMessage());
                    }
                }
            }
        });

        return ['new_count' => $newCount, 'existing_count' => $existingCount];
    }

    /**
     * MOTOR 3: Processa listas externas APENAS para acesso ao sistema (sem pesquisa vinculada).
     */
    private function processSystemInvitations(User $sender, array $usersData, ?string $customMessage = null): array
    {
        $newCount = 0;
        $existingCount = 0;
        $nativeColumns = ['name', 'email', 'phone', 'crm', 'crm_state'];

        DB::transaction(function () use ($usersData, $sender, $nativeColumns, $customMessage, &$newCount, &$existingCount) {

            foreach ($usersData as $rowData) {
                $rowData = array_change_key_case($rowData, CASE_LOWER);

                if (empty($rowData['email']))
                    continue;

                $email = trim(strtolower($rowData['email']));
                $responder = Responder::where('email', $email)->first();

                $metadata = [];
                foreach ($rowData as $key => $value) {
                    if (!in_array($key, $nativeColumns)) {
                        $metadata[$key] = $value;
                    }
                }

                if ($responder) {
                    $updatedMetadata = array_merge($responder->metadata ?? [], $metadata);
                    $responder->update([
                        'metadata' => !empty($updatedMetadata) ? $updatedMetadata : null
                    ]);
                    $existingCount++;
                } else {
                    // --- NOVO USUÁRIO AVULSO ---

                    // 🌟 1. Gera senha plana
                    $plainPassword = Str::random(10);

                    $newResponder = Responder::create([
                        'name' => trim($rowData['name'] ?? 'Profissional'),
                        'email' => $email,
                        'phone' => $rowData['phone'] ?? null,
                        'crm' => $rowData['crm'] ?? null,
                        'crm_state' => $rowData['crm_state'] ?? null,
                        'active' => true,
                        'password' => Hash::make($plainPassword), // Hash no banco
                        'inviter_id' => $sender->id,
                        'metadata' => !empty($metadata) ? $metadata : null
                    ]);

                    SystemInvitation::create([
                        'sender_id' => $sender->id,
                        'name' => $newResponder->name,
                        'email' => $email,
                        'phone' => $newResponder->phone,
                        'status' => 'pending',
                        'metadata' => !empty($metadata) ? $metadata : null
                    ]);

                    $newCount++;

                    // 🌟 2. Disparo de E-mail de Boas Vindas com as credenciais provisórias
                    $loginUrl = env('FRONTEND_URL', env('APP_URL', 'https://amb-pesquisas.org.br')) . '/login';
                    $baseMsg = $customMessage ?? "Você está sendo convidado por {$sender->name} para se credenciar na Plataforma Científica AMB.";

                    $msgWithCredentials = $baseMsg . "\n\nPara acessar o sistema, utilize seus dados abaixo:\n\nE-mail de acesso: {$email}\nSenha Provisória: {$plainPassword}\n\n*Recomendamos alterar sua senha no menu Perfil logo após o seu primeiro acesso.";

                    try {
                        Mail::to($email)->send(new PlatformInvitationMail($msgWithCredentials, $loginUrl));
                    } catch (\Exception $e) {
                        Log::warning("Falha SMTP Novo Cadastro Avulso: " . $e->getMessage());
                    }
                }
            }
        });

        return ['new_count' => $newCount, 'existing_count' => $existingCount];
    }

    /**
     * Importar usuários externos apenas para o Sistema (Avulso)
     * @tags Público-Alvo
     */
    public function importSystemTargets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'nullable|string',
            'users' => 'required|array|min:1',
            'users.*.name' => 'required|string|max:255',
            'users.*.email' => 'required|email',
            'users.*.phone' => 'nullable|string',
            'users.*.crm' => 'nullable',
            'users.*.crm_state' => 'sometimes|nullable|string|size:2',
        ]);

        $rawUsers = $request->input('users');

        $result = $this->processSystemInvitations($request->user(), $rawUsers, $data['message'] ?? null);

        return response()->json(['message' => 'Lote institucional processado.', 'new_count' => $result['new_count']], 200);
    }

    /**
     * Disparo Genérico Multi-Função (Acionado pela Central de Disparos antiga)
     * @tags Público-Alvo
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'search_id' => 'required|exists:searches,id',
            'target_type' => 'required|in:manager,responder',
            'strategy' => 'required|in:individual,mass_email,specialty',
            'recipient_id' => 'required_if:strategy,individual|integer',
            'emails' => 'required_if:strategy,mass_email|array',
            'emails.*' => 'email',
            'specialties' => 'required_if:strategy,specialty|array',
            'specialties.*' => 'exists:specialties,id',
        ]);

        $search = Search::findOrFail($data['search_id']);
        $this->validateManagementPermission($user, $search);
        $this->validateSearchIsOpen($search);

        $initialStatus = $search->status === 'published' ? 'sent' : 'pending_publish';

        // Lógica Exclusiva para Co-Gestores (Diretores)
        if ($data['target_type'] === 'manager') {
            if ($data['strategy'] === 'individual') {
                $target = User::findOrFail($data['recipient_id']);
                if ($user->type === 'director' && $target->type !== 'director')
                    return response()->json(['message' => 'Permissão negada.'], 403);

                SearchInvitation::updateOrCreate(
                    ['search_id' => $search->id, 'email' => $target->email],
                    ['sender_id' => $user->id, 'name' => $target->name, 'status' => $initialStatus, 'delivery_status' => 'standby', 'responder_id' => null]
                );
            }
            return response()->json(['message' => 'Convite para gestão enviado.']);
        }

        // Lógica para Respondentes - Redireciona para os Motores DRY
        if ($data['strategy'] === 'individual') {
            $this->processInternalInvitations($user, $search, [$data['recipient_id']]);
        } elseif ($data['strategy'] === 'specialty') {
            $responderIds = Responder::whereHas('specialties', fn($q) => $q->whereIn('specialties.id', $data['specialties']))->pluck('id')->toArray();
            $this->processInternalInvitations($user, $search, $responderIds);
        } elseif ($data['strategy'] === 'mass_email') {
            $formattedUsers = array_map(fn($email) => ['email' => $email, 'name' => 'Profissional'], $data['emails']);
            $this->processExternalInvitations($user, $search, $formattedUsers);
        }

        return response()->json(['message' => 'Convites processados e empilhados com sucesso!']);
    }

    // =========================================================================
    // LEITURAS E OUTROS HELPERS
    // =========================================================================
    public function getTargets(Request $request, $id): JsonResponse
    {
        // 🌟 CORREÇÃO: Carregar os dados completos do médico e suas especialidades!
        $invitations = Search::findOrFail($id)->invitations()
            ->with(['responder:id,name,email,crm,crm_state,active', 'responder.specialties:id,name'])
            ->get();

        $completed = SearchAnswer::where('search_id', $id)->where('progress_status', 'completed')->pluck('responder_id')->toArray();
        $inProgress = SearchAnswer::where('search_id', $id)->where('progress_status', 'in_progress')->pluck('responder_id')->toArray();

        $invitations->map(function ($invite) use ($completed, $inProgress) {
            $invite->has_completed = in_array($invite->responder_id, $completed);
            $invite->is_in_progress = in_array($invite->responder_id, $inProgress);
            $invite->not_started = (!$invite->has_completed && !$invite->is_in_progress);
            return $invite;
        });

        return response()->json($invitations);
    }

    /**
     * Listar Diretores elegíveis do sistema para co-gestão
     */
    public function getEligibleManagers(Request $request, $id): JsonResponse
    {
        $search = Search::findOrFail($id);

        // Lista usuários do tipo director (e master se quiser) exceto quem já gerencia ou é o autor
        $managedUserIds = $search->managers()->pluck('user_id')->toArray();

        $query = User::where('type', 'director')
            ->where('active', 1)
            ->where('id', '!=', $search->author_id)
            ->whereNotIn('id', $managedUserIds);

        if ($request->filled('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"));
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * Convidar Co-Gestor (Interno da base ou Externo de fora do sistema)
     */
    public function inviteCoManager(Request $request, $id): JsonResponse
    {
        $sender = $request->user();
        $search = Search::findOrFail($id);

        $this->validateManagementPermission($sender, $search);
        $this->validateSearchIsOpen($search);

        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'required_if:is_external,true|string|max:255',
            'is_external' => 'required|boolean'
        ]);

        $email = trim(strtolower($data['email']));
        $initialStatus = 'sent';

        return DB::transaction(function () use ($email, $data, $search, $sender, $initialStatus) {

            if ($data['is_external']) {
                if (User::where('email', $email)->exists()) {
                    return response()->json(['message' => 'Este e-mail já pertence a um usuário do sistema.'], 422);
                }

                $passwordProvisoria = Str::random(10);

                // 1. Cadastra o usuário de fora como Diretor
                $newUser = User::create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'type' => 'director',
                    'active' => true,
                    'password' => Hash::make($passwordProvisoria),
                    'inviter_id' => $sender->id
                ]);

                // 🌟 NOVO: Adiciona o novo diretor na tabela search_managers imediatamente
                $search->managers()->syncWithoutDetaching([$newUser->id]);

                // 2. Cria o registro de Convite de Pesquisa
                SearchInvitation::create([
                    'search_id' => $search->id,
                    'sender_id' => $sender->id,
                    'email' => $email,
                    'name' => $newUser->name,
                    'status' => $initialStatus,
                    'delivery_status' => 'success_email',
                    'responder_id' => null
                ]);

                // 3. Dispara e-mail contendo credenciais temporárias
                $emailBody = "Olá, {$newUser->name}.\n\nO diretor {$sender->name} está te convidando para co-gerenciar a pesquisa científica: \"{$search->title}\".\n\nComo você não possui cadastro, criamos credenciais temporárias para o seu primeiro acesso.\n\nLink: https://amb-pesquisas.org.br/login\nE-mail: {$email}\nSenha Provisória: {$passwordProvisoria}\n\nEntre no sistema e altere sua senha no seu primeiro acesso.";
                Mail::raw($emailBody, fn($msg) => $msg->to($email)->subject("Convite de Co-gestão Científica - AMB"));

                return response()->json(['message' => 'Novo diretor convidado e credenciais geradas com sucesso!']);
            }

            // -------------------------------------------------------------------------
            // CONVITE PARA DIRETOR JÁ EXISTENTE NA BASE
            // -------------------------------------------------------------------------
            $targetUser = User::where('email', $email)->where('type', 'director')->firstOrFail();

            // Adiciona o diretor da base na tabela search_managers imediatamente
            $search->managers()->syncWithoutDetaching([$targetUser->id]);

            SearchInvitation::updateOrCreate(
                ['search_id' => $search->id, 'email' => $email],
                [
                    'sender_id' => $sender->id,
                    'name' => $targetUser->name,
                    'status' => $initialStatus,
                    'delivery_status' => 'success_email',
                    'responder_id' => null
                ]
            );

            $emailBody = "Olá, {$targetUser->name}.\n\nO diretor {$sender->name} incluiu você como co-gestor na pesquisa científica: \"{$search->title}\".\n\nAcesse seu painel administrativo para acessar a pesquisa.";
            Mail::raw($emailBody, fn($msg) => $msg->to($email)->subject("Novo Convite de Co-gestão - " . $search->title));

            return response()->json(['message' => 'Diretor da base convidado com sucesso!']);
        });
    }

    /**
     * Listar os diretores co-gestores já convidados para esta pesquisa
     */
    public function getInvitedManagers($id): JsonResponse
    {
        // Traz convites vinculados à pesquisa que não possuem responder_id (ou seja, são co-gestores/diretores)
        $invitations = DB::table('search_invitations')
            ->where('search_id', $id)
            ->whereNull('responder_id')
            ->select('id', 'name', 'email', 'status', 'sender_id')
            ->orderBy('name')
            ->get();

        return response()->json($invitations);
    }

    /**
     * Remover/Revogar convite de co-gestão de um diretor
     */
    public function removeCoManager(Request $request, $id, $invitationId): JsonResponse
    {
        $user = $request->user();
        $search = Search::findOrFail($id);

        // Busca o convite de co-gestão específico
        $invitation = DB::table('search_invitations')
            ->where('id', $invitationId)
            ->where('search_id', $id)
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Convite não localizado.'], 404);
        }

        // Apenas o autor original da pesquisa (quem criou) ou um usuário Master pode remover
        if ($user->type !== 'master' && $search->author_id !== $user->id) {
            return response()->json(['message' => 'Operação negada: Apenas o diretor autor do projeto científico pode revogar co-gestores.'], 403);
        }

        DB::transaction(function () use ($invitation, $search) {
            // 1. Remove da tabela de convites da pesquisa
            DB::table('search_invitations')->where('id', $invitation->id)->delete();

            // 2. Busca o Usuário associado a este e-mail
            $invitedUser = User::where('email', $invitation->email)->first();

            if ($invitedUser) {
                // 3. Desvincula o usuário da tabela pivô de gerenciadores da pesquisa atual
                $search->managers()->detach($invitedUser->id);

                // 4. LÓGICA DE EXCLUSÃO (Interno vs Externo)
                // Verifica se o usuário ainda possui convites para outras pesquisas
                $hasOtherInvitations = DB::table('search_invitations')
                    ->where('email', $invitedUser->email)
                    ->exists();

                // Verifica se o usuário já gerencia outras pesquisas (além desta que acabamos de remover)
                // Usamos o próprio relacionamento do Eloquent para contar se ainda sobrou alguma
                $hasOtherManagements = DB::table('search_managers') // Substitua 'search_user' pelo nome real da sua tabela pivô, ex: 'search_managers' se for diferente
                    ->where('user_id', $invitedUser->id)
                    ->exists();

                // Se o usuário foi convidado por alguém (inviter_id não nulo) 
                // E não possui mais NENHUM vínculo com outras pesquisas no sistema...
                // Significa que ele era um perfil Externo criado só para isso. Removemos do projeto todo.
                if (!$hasOtherInvitations && !$hasOtherManagements && $invitedUser->inviter_id !== null) {
                    $invitedUser->update(['active' => 0]);
                    $invitedUser->delete();
                }
            }
        });

        return response()->json(['message' => 'Co-gestor removido e acessos revogados com sucesso.']);
    }

    public function removeTarget(Request $request, $search_id, $user_id): JsonResponse
    {
        // Resgata a flag opcional. Se não for enviada, assume false (protege legados)
        $removeFromSystem = filter_var($request->query('system', false), FILTER_VALIDATE_BOOLEAN);

        if (SearchAnswer::where('search_id', $search_id)->where('responder_id', $user_id)->exists()) {
            return response()->json(['message' => 'O usuário iniciou o preenchimento. Remoção abortada.'], 409);
        }

        $responder = Responder::findOrFail($user_id);
        $search = Search::findOrFail($search_id);

        DB::transaction(function () use ($search, $responder, $removeFromSystem) {
            // 1. Remove o convite específico desta pesquisa
            $search->invitations()->where('email', $responder->email)->delete();

            // 2. Lógica de Exclusão do Sistema (A mesma regra dos Diretores)
            if ($removeFromSystem) {
                // Checa se o usuário tem convites para responder OUTRAS pesquisas
                $hasOtherInvitations = DB::table('search_invitations')
                    ->where('email', $responder->email)
                    ->exists();

                // Checa se o usuário já tem respostas consolidadas no banco de dados
                $hasAnswers = DB::table('search_answers')
                    ->where('responder_id', $responder->id)
                    ->exists();

                // Regra de Ouro: Só destrói a conta se ele estiver completamente "órfão"
                if (!$hasOtherInvitations && !$hasAnswers) {
                    // Limpa também eventuais convites de sistema que estivessem aguardando
                    DB::table('system_invitations')->where('email', $responder->email)->delete();
                    
                    // Inativa e aplica o Soft Delete
                    $responder->update(['active' => 0]);
                    $responder->delete();
                }
            }
        });

        return response()->json(['message' => 'Remoção processada com sucesso.']);
    }


    // =========================================================================
    // MOTORES ÚNICOS (DRY) DE PROCESSAMENTO DE CONVITES E VALIDAÇÃO
    // =========================================================================

    /**
     * MOTOR 1: Processa convites para Médicos que já possuem cadastro interno.
     */
    private function processInternalInvitations(User $sender, Search $search, array $responderIds, ?string $customMessage = null): int
    {
        $this->validateManagementPermission($sender, $search);
        $this->validateSearchIsOpen($search);

        $initialStatus = $search->status === 'published' ? 'sent' : 'pending_publish';
        $surveyUrl = "https://amb-pesquisas.org.br/responder/{$search->id}";
        $boundCount = 0;

        $responders = Responder::whereIn('id', $responderIds)->get();

        foreach ($responders as $responder) {
            $invitation = SearchInvitation::updateOrCreate(
                ['search_id' => $search->id, 'email' => $responder->email],
                ['sender_id' => $sender->id, 'name' => $responder->name, 'phone' => $responder->phone, 'status' => $initialStatus, 'delivery_status' => 'standby', 'responder_id' => $responder->id]
            );

            if ($invitation->wasRecentlyCreated)
                $boundCount++;

            if ($search->status === 'published') {
                $emailBody = $customMessage
                    ? "Olá, {$responder->name}.\n\n{$customMessage}\n\nAcesse a pesquisa: {$surveyUrl}"
                    : "A pesquisa científica da AMB: \"{$search->title}\" está aberta para coleta.\n\nAcesse: {$surveyUrl}";

                try {
                    Mail::raw($emailBody, fn($msg) => $msg->to($responder->email)->subject("Convite Científico AMB: " . $search->title));
                    $invitation->update(['delivery_status' => 'success_email']);
                } catch (\Exception $e) {
                    Log::error("Falha SMTP Custom Batch: " . $e->getMessage());
                    $invitation->update(['delivery_status' => 'failed']);
                }
            }
        }
        return $boundCount;
    }

    /**
     * Listar Médicos Elegíveis para a Pesquisa
     */
    public function getEligibleUsers(Request $request, $id): JsonResponse
    {
        $search = Search::with('specialties')->findOrFail($id);
        
        $invitedEmails = $search->invitations()->pluck('email')->toArray();
        $query = Responder::where('active', 1);

        if (!empty($invitedEmails)) {
            $query->whereNotIn('email', $invitedEmails);
        }

        // Regra do Projeto: Se a pesquisa tem especialidades, só foca nelas
        if ($search->specialties->count() > 0) {
            $query->whereHas('specialties', fn($q) => $q->whereIn('specialties.id', $search->specialties->pluck('id')->toArray()));
        }

        // ==========================================
        // 🌟 APLICAÇÃO DOS FILTROS DO FRONTEND
        // ==========================================

        // 1. Busca por Texto (Nome, Email ou CRM)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('crm', 'like', "%{$searchTerm}%");
            });
        }

        // 2. Filtro de Especialidade
        if ($request->filled('specialty') && $request->specialty !== 'all') {
            if ($request->specialty === 'none') {
                $query->doesntHave('specialties');
            } else {
                $query->whereHas('specialties', fn($q) => $q->where('specialties.id', $request->specialty));
            }
        }

        // 3. Cruzamento Histórico (Avançado)
        if ($request->filled('past_search_id') && $request->filled('past_engagement')) {
            $pastSearchId = $request->past_search_id;
            $engagements = is_array($request->past_engagement) ? $request->past_engagement : explode(',', $request->past_engagement);

            $query->where(function ($q) use ($pastSearchId, $engagements) {
                if (in_array('invited', $engagements)) {
                    $q->orWhereHas('searchInvitations', fn($sq) => $sq->where('search_id', $pastSearchId));
                }
                if (in_array('completed', $engagements)) {
                    // 🌟 CORREÇÃO: Usando 'answers'
                    $q->orWhereHas('answers', fn($sq) => $sq->where('search_id', $pastSearchId)->where('progress_status', 'completed'));
                }
                if (in_array('in_progress', $engagements)) {
                    // 🌟 CORREÇÃO: Usando 'answers'
                    $q->orWhereHas('answers', fn($sq) => $sq->where('search_id', $pastSearchId)->where('progress_status', 'in_progress'));
                }
                if (in_array('abstention', $engagements)) {
                    $q->orWhere(function ($subQ) use ($pastSearchId) {
                        // 🌟 CORREÇÃO: Usando 'answers'
                        $subQ->whereHas('searchInvitations', fn($sq) => $sq->where('search_id', $pastSearchId))
                             ->whereDoesntHave('answers', fn($sq) => $sq->where('search_id', $pastSearchId));
                    });
                }
            });
        }

        $paginated = $query->with('specialties:id,name')->orderBy('name')->paginate(10);
        return response()->json($paginated);
    }

    /**
     * Convidar em Massa via Filtros
     * Captura todos os usuários do filtro, ignorando as páginas, e dispara o convite!
     */
    public function massInviteByFilter(Request $request, $id): JsonResponse
    {
        $search = Search::findOrFail($id);
        $user = $request->user();

        // 1. Reconstrói a mesma query exata
        $invitedEmails = $search->invitations()->pluck('email')->toArray();
        $query = Responder::where('active', 1);

        // Prevenção de erro: Só aplica o filtro se existirem e-mails para ignorar
        if (!empty($invitedEmails)) {
            $query->whereNotIn('email', $invitedEmails);
        }

        if ($search->specialties->count() > 0) {
            $query->whereHas('specialties', fn($q) => $q->whereIn('specialties.id', $search->specialties->pluck('id')->toArray()));
        }
        
        if ($request->filled('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                      ->orWhere('email', 'like', "%{$request->search}%"));
        }
        
        if ($request->filled('specialty') && $request->specialty !== 'all') {
            if ($request->specialty === 'none') {
                $query->doesntHave('specialties');
            } else {
                $query->whereHas('specialties', fn($q) => $q->where('specialties.id', $request->specialty));
            }
        }

        // 2. Extrai absolutamente TODOS os IDs que deram match no banco
        $responderIds = $query->pluck('id')->toArray();

        // 🌟 Prevenção Extra: Se a busca não encontrou ninguém, avisa o Front
        if (empty($responderIds)) {
            return response()->json(['message' => 'Nenhum usuário novo foi encontrado neste filtro para ser convidado.'], 400);
        }

        // 3. Envia os IDs para o motor de convites interno
        $boundCount = $this->processInternalInvitations($user, $search, $responderIds);

        return response()->json([
            'message' => "Lote massivo processado! Foram disparados {$boundCount} convites com sucesso.", 
            'bound_count' => $boundCount
        ]);
    }

    /**
     * Resumo Analítico de Convites (Consumido pelos Cards Superiores)
     */
    public function getInvitationSummary(Request $request, $id): JsonResponse
    {
        $search = Search::with('specialties')->findOrFail($id);
        $query = Responder::where('active', 1);

        if ($search->specialties->count() > 0) {
            $query->whereHas('specialties', fn($q) => $q->whereIn('specialties.id', $search->specialties->pluck('id')->toArray()));
        }

        // 🌟 CORREÇÃO 2: Ampliamos as métricas exportadas para o Frontend conseguir atualizar os cards
        $invitations = $search->invitations();

        return response()->json([
            'total' => $query->count(),
            'internal' => (clone $query)->whereNull('inviter_id')->count(),
            'external' => (clone $query)->whereNotNull('inviter_id')->count(),
            'invited' => (clone $invitations)->count(),
            'accepted' => (clone $invitations)->where('status', 'accepted')->count(),
            'declined' => (clone $invitations)->where('status', 'declined')->count(),
            'standby' => (clone $invitations)->where('status', 'pending_publish')->count(),
            'sent' => (clone $invitations)->where('status', 'sent')->count(),
        ]);
    }
    private function validateManagementPermission(User $user, Search $search): void
    {
        $isAuthorOrManager = $search->author_id === $user->id || $search->managers()->where('user_id', $user->id)->exists();
        if ($user->type !== 'master' && !$isAuthorOrManager)
            abort(response()->json(['message' => 'Permissão de governança negada para esta pesquisa.'], 403));
    }

    private function validateSearchIsOpen(Search $search): void
    {
        if (in_array($search->status, ['closed', 'archived'])) {
            abort(response()->json(['message' => 'Ações de convite bloqueadas. A pesquisa encontra-se encerrada ou arquivada.'], 422));
        }
    }

    // =========================================================================
    // MOTOR DE DISPARO (BROADCAST)
    // =========================================================================

    /**
     * Dispara as notificações para os convites em standby quando a pesquisa é publicada.
     */
    public function broadcastSearchLaunch(Search $search): void
    {
        // 1. Buscar os convites vinculados a esta pesquisa em 'pending_publish' e 'standby'
        $invitations = $search->invitations()
            ->where('status', 'pending_publish')
            ->where('delivery_status', 'standby')
            ->with('responder')
            ->get();

        if ($invitations->isEmpty()) {
            Log::info("Nenhum convite em standby encontrado para a pesquisa ID: {$search->id}");
            return;
        }

        foreach ($invitations as $invitation) {
            $sent = false;
            $channelSuccess = 'failed';

            if (!$invitation->responder) {
                $invitation->update([
                    'status' => 'pending_publish',
                    'delivery_status' => 'failed'
                ]);
                continue;
            }

            // 2. Tentativa 1: Enviar por E-mail
            if ($this->sendEmailNotification($invitation)) {
                $sent = true;
                $channelSuccess = 'success_email';
            } else {
                // 3. Tentativa 2: WhatsApp (Fallback)
                if ($this->sendWhatsAppNotification($invitation)) {
                    $sent = true;
                    $channelSuccess = 'success_whatsapp';
                }
            }

            // 4. Atualizar o status baseado nos resultados
            if ($sent) {
                // Mantemos um sent_at caso a tabela do banco suporte
                $updateData = [
                    'status' => 'sent',
                    'delivery_status' => $channelSuccess
                ];

                // Se sua migration tiver a coluna sent_at descomente a linha abaixo:
                // $updateData['sent_at'] = now(); 

                $invitation->update($updateData);
            } else {
                $invitation->update([
                    'status' => 'pending_publish',
                    'delivery_status' => 'failed'
                ]);

                Log::error("Falha crítica ao enviar convite ID: {$invitation->id} por e-mail e WhatsApp.");
            }
        }

        Log::info("Motor de broadcast finalizado para a pesquisa ID: {$search->id}. Processados: " . $invitations->count());
    }

    /**
     * Helper de Disparo por E-mail
     */
    protected function sendEmailNotification($invitation): bool
    {
        try {
            $email = $invitation->responder->email;

            if (empty($email))
                return false;

            $surveyUrl = "https://amb-pesquisas.org.br/responder/{$invitation->search->id}";
            $emailBody = "A pesquisa científica da AMB: \"{$invitation->search->title}\" está aberta para coleta.\n\nAcesse: {$surveyUrl}";

            Mail::raw($emailBody, fn($msg) => $msg->to($email)->subject("Convite Científico AMB: " . $invitation->search->title));

            return true;
        } catch (\Exception $e) {
            Log::warning("Erro ao disparar e-mail para o convite {$invitation->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper de Disparo por WhatsApp
     */
    protected function sendWhatsAppNotification($invitation): bool
    {
        try {
            $phone = $invitation->responder->phone;

            if (empty($phone))
                return false;

            // Exemplo de uso real no futuro com algum SDK de WhatsApp (Twilio/Z-API, etc):
            // app(WhatsAppService::class)->sendMessage($phone, "Olá, uma nova pesquisa...");

            return true;
        } catch (\Exception $e) {
            Log::warning("Erro ao disparar WhatsApp para o convite {$invitation->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pré-validar e-mails da planilha antes de confirmar a carga (Suporta Sistema e Pesquisa)
     */
    public function validateCsv(Request $request): JsonResponse
    {
        $request->validate([
            'search_id' => 'nullable|string',
            'users' => 'required|array|min:1',
            'users.*.email' => 'required|email'
        ]);

        $searchId = $request->input('search_id');

        $emails = collect($request->input('users'))
            ->map(fn($u) => trim(strtolower($u['email'])))
            ->filter()
            ->unique()
            ->toArray();

        $totalUnique = count($emails);

        if ($searchId && $searchId !== 'avulso') {

            $alreadyInvitedInSearch = DB::table('search_invitations')
                ->where('search_id', $searchId)
                ->whereIn('email', $emails)
                ->count();

            $alreadyActiveInSystemOnly = Responder::whereIn('email', $emails)
                ->where('active', 1)
                ->whereNotIn('email', function ($q) use ($searchId) {
                    $q->select('email')->from('search_invitations')->where('search_id', $searchId);
                })
                ->count();

            $newTargets = max(0, $totalUnique - $alreadyInvitedInSearch - $alreadyActiveInSystemOnly);

            return response()->json([
                'is_search' => true,
                'already_invited' => $alreadyInvitedInSearch,
                'already_active' => $alreadyActiveInSystemOnly,
                'new_targets' => $newTargets,
                'total' => $totalUnique
            ]);
        }

        $alreadyActive = Responder::whereIn('email', $emails)
            ->where('active', 1)
            ->count();

        $reinvite = Responder::whereIn('email', $emails)
            ->where('active', 0)
            ->whereIn('email', function ($query) {
                $query->select('email')
                    ->from('system_invitations')
                    ->whereIn('status', ['pending', 'expired']);
            })
            ->count();

        $newTargets = max(0, $totalUnique - $alreadyActive - $reinvite);

        return response()->json([
            'is_search' => false,
            'already_active' => $alreadyActive,
            'reinvite' => $reinvite,
            'new_targets' => $newTargets,
            'total' => $totalUnique
        ]);
    }
}