<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Learning\LearningMaterial;
use App\Services\Learning\LearningAccess;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Class materials: documents (private files), video lessons, recorded lessons and links. permission: materials.* */
class MaterialController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected LearningAccess $access) {}

    public function index(Request $request): View
    {
        $this->authorize('materials.view');
        $materials = $this->access->scopeFor($request->user(), LearningMaterial::query())
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,name_bn', 'creator:id,name'])
            ->when(in_array($request->input('type'), LearningMaterial::TYPES, true), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->integer('class_id'), fn ($q, $c) => $q->where('school_class_id', $c))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('title', 'like', "%$t%"))
            ->latest('id')->paginate(25)->withQueryString();

        return view('branch.learning.materials.index', ['materials' => $materials, 'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id')] + $this->shared($request));
    }

    public function create(Request $request): View
    {
        $this->authorize('materials.create');

        return view('branch.learning.materials.form', $this->formData($request, new LearningMaterial(['type' => 'document'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('materials.create');
        $data = $this->validated($request);
        $this->assertTeaches($request, $data);

        $material = new LearningMaterial(collect($data)->only((new LearningMaterial)->getFillable())->all());
        $material->forceFill(['academic_session_id' => \App\Models\Academic\AcademicSession::query()->current()->value('id'), 'created_by' => $request->user()->id]);
        $this->applyFile($material, $request);
        $this->applyPublication($material, $request);
        $material->save();

        return redirect()->route($this->routePrefix($request).'learning.materials.index')->with('success', __('Material saved.'));
    }

    public function edit(Request $request, LearningMaterial $material): View
    {
        $this->assertManage($request, $material, 'update');

        return view('branch.learning.materials.form', $this->formData($request, $material));
    }

    public function update(Request $request, LearningMaterial $material): RedirectResponse
    {
        $this->assertManage($request, $material, 'update');
        $data = $this->validated($request, $material);
        $this->assertTeaches($request, $data);

        $material->fill(collect($data)->only($material->getFillable())->except(['type'])->all());
        $this->applyFile($material, $request);
        $this->applyPublication($material, $request);
        $material->save();

        return redirect()->route($this->routePrefix($request).'learning.materials.index')->with('success', __('Material saved.'));
    }

    public function destroy(Request $request, LearningMaterial $material): RedirectResponse
    {
        $this->assertManage($request, $material, 'delete');
        if ($material->file_path) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();

        return redirect()->route($this->routePrefix($request).'learning.materials.index')->with('success', __('Material deleted.'));
    }

    public function file(Request $request, LearningMaterial $material): StreamedResponse
    {
        $this->assertManage($request, $material, 'view');
        abort_unless($material->file_path && Storage::disk('local')->exists($material->file_path), 404);

        return Storage::disk('local')->download($material->file_path, $material->file_name);
    }

    protected function assertManage(Request $request, LearningMaterial $material, string $ability): void
    {
        $this->authorize('materials.'.$ability);
        abort_unless($this->access->manages($request->user(), $material), 404);
    }

    protected function assertTeaches(Request $request, array $data): void
    {
        $section = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        if (! $this->access->canTeach($request->user(), (int) $data['school_class_id'], $section)) {
            throw ValidationException::withMessages(['section_id' => __('You can only share material with the sections you teach.')]);
        }
        if (! $this->access->subjectBelongsToClass((int) $data['school_class_id'], filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null)) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in the chosen class.')]);
        }
    }

    protected function validated(Request $request, ?LearningMaterial $material = null): array
    {
        $type = $material?->type ?? $request->input('type');
        $data = $request->validate([
            'type' => [$material ? 'prohibited' : 'required', Rule::in(LearningMaterial::TYPES)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes', 'id', true)],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections', 'id', true)],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects', 'id', true)],
            'url' => [$type === 'document' ? 'nullable' : 'required', 'nullable', 'url:http,https', 'max:500'],
            'file' => [$type === 'document' && ! $material?->file_path ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,png,jpg,jpeg,webp', 'max:20480'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $data['type'] = $type;

        return $data;
    }

    protected function applyFile(LearningMaterial $material, Request $request): void
    {
        if ($material->type !== 'document') {
            $material->url = $request->input('url');

            return;
        }
        if ($file = $request->file('file')) {
            if ($material->file_path) {
                Storage::disk('local')->delete($material->file_path);
            }
            $material->file_path = $file->store(tenant()->branch()->storagePath('materials'), 'local');
            $material->file_name = mb_substr($file->getClientOriginalName(), 0, 191);
            $material->file_size = $file->getSize();
        }
        $material->url = null;
    }

    protected function applyPublication(LearningMaterial $material, Request $request): void
    {
        $publish = $request->boolean('is_published');
        $material->is_published = $publish;
        $material->published_at = $publish ? ($material->published_at ?? now()) : null;
    }

    protected function formData(Request $request, LearningMaterial $material): array
    {
        return [
            'material' => $material,
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => $this->access->sections($request->user())->map(fn ($s) => ['id' => $s->id, 'class' => $s->school_class_id, 'name' => $s->name])->values(),
            'subjects' => ClassSubject::query()->with('subject:id,name,name_bn')->get()->map(fn ($c) => ['class' => $c->school_class_id, 'id' => $c->subject_id, 'name' => $c->subject?->displayName()])
                ->filter(fn ($r) => $r['name'])->unique(fn ($r) => $r['class'].'-'.$r['id'])->values(),
            'sectionRequired' => $this->access->isTeacher($request->user()),
        ] + $this->shared($request);
    }

    protected function shared(Request $request): array
    {
        return ['layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request)];
    }
}
