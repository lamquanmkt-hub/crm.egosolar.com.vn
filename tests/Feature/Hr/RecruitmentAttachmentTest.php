<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RecruitmentAttachmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs($this->userWithRole('admin'));
    }

    private function candidate(): int
    {
        return DB::table('hr_recruitment_candidates')->insertGetId(['full_name'=>'TEST Tệp tuyển dụng','status'=>'archived','created_at'=>now(),'updated_at'=>now()]);
    }

    public function test_all_four_record_types_create_and_append_files_without_replacing_old_files(): void
    {
        $candidate = $this->candidate();
        $cases = [
            'requests'=>['department'=>'TEST','position'=>'TEST','quantity'=>1],
            'candidates'=>['full_name'=>'TEST Upload','status'=>'archived'],
            'interviews'=>['candidate_id'=>$candidate,'status'=>'done','evaluation_result'=>'passed'],
            'offers'=>['candidate_id'=>$candidate,'status'=>'accepted','onboarding_status'=>'collecting_docs'],
        ];
        foreach ($cases as $entity=>$data) {
            $this->post(route('hr.recruitment.'.$entity.'.store'), $data + ['attachments'=>[UploadedFile::fake()->createWithContent('Hồ sơ TEST.txt','TEST Nội dung')]])->assertSessionHasNoErrors()->assertRedirect();
            $file = DB::table('hr_recruitment_attachments')->where('entity',$entity)->orderByDesc('id')->first();
            $this->assertNotNull($file);
            Storage::disk('local')->assertExists($file->path);
            $this->put(route('hr.recruitment.'.$entity.'.update',$file->record_id), $data + ['attachments'=>[UploadedFile::fake()->createWithContent('Bổ sung.txt','TEST Bổ sung')]])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(2, DB::table('hr_recruitment_attachments')->where('entity',$entity)->where('record_id',$file->record_id)->count());
            $this->get(route('hr.recruitment.attachments.preview',[$entity,$file->record_id,$file->id]))->assertOk()->assertSee('Hồ sơ TEST.txt');
            $this->get(route('hr.recruitment.attachments.content',[$entity,$file->record_id,$file->id]))->assertOk()->assertSee('TEST Nội dung');
            $download = $this->get(route('hr.recruitment.attachments.download',[$entity,$file->record_id,$file->id]))->assertDownload();
            $this->assertStringContainsString(rawurlencode('Hồ sơ TEST.txt'), $download->headers->get('Content-Disposition'));
        }
        foreach (['requests','screening','interviews','evaluations','offers','onboarding','archives'] as $stage) {
            $this->get(route('hr.recruitment.'.$stage))->assertOk()->assertSee('Thêm tệp từ máy tính')->assertSee('Xem trước');
        }
    }

    public function test_failed_scalar_validation_does_not_store_files_or_change_the_record(): void
    {
        $id = $this->candidate();
        $this->put(route('hr.recruitment.candidates.update',$id), ['full_name'=>'','attachments'=>[UploadedFile::fake()->createWithContent('test.txt','TEST')]])->assertSessionHasErrors('full_name');
        $this->assertSame('TEST Tệp tuyển dụng',DB::table('hr_recruitment_candidates')->where('id',$id)->value('full_name'));
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_deleting_record_removes_its_files_and_missing_record_cannot_accept_upload(): void
    {
        $file = $this->upload(UploadedFile::fake()->createWithContent('delete-test.txt','TEST'));
        $this->delete(route('hr.recruitment.candidates.destroy',$file->record_id))->assertRedirect();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertDatabaseMissing('hr_recruitment_attachments',['id'=>$file->id]);
        $this->put(route('hr.recruitment.candidates.update',$file->record_id),['full_name'=>'TEST Missing','attachments'=>[UploadedFile::fake()->createWithContent('missing.txt','TEST')]])->assertNotFound();
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_invalid_extension_count_and_size_leave_no_files_or_rows(): void
    {
        $real = tempnam(sys_get_temp_dir(),'rec-invalid');
        file_put_contents($real,'<html>Not a PDF</html>');
        foreach ([
            [UploadedFile::fake()->createWithContent('test.html','<script>alert(1)</script>')],
            array_map(fn()=>UploadedFile::fake()->createWithContent('test.txt','TEST'),range(1,9)),
            [UploadedFile::fake()->create('huge.pdf',11000,'application/pdf')],
            [new UploadedFile($real,'fake.pdf',null,null,true)],
        ] as $files) {
            $this->post(route('hr.recruitment.candidates.store'), ['full_name'=>'TEST Invalid','attachments'=>$files])->assertSessionHasErrors();
        }
        @unlink($real);
        $this->assertSame([],Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('hr_recruitment_candidates',['full_name'=>'TEST Invalid']);
    }

    private function upload(UploadedFile $file): object
    {
        $id = $this->candidate();
        $this->put(route('hr.recruitment.candidates.update',$id),['full_name'=>'TEST Tệp tuyển dụng','attachments'=>[$file]])->assertSessionHasNoErrors();
        return DB::table('hr_recruitment_attachments')->where('entity','candidates')->where('record_id',$id)->first();
    }

    public function test_private_files_require_login_hr_permission_and_exact_parent(): void
    {
        $file = $this->upload(UploadedFile::fake()->createWithContent('private.txt','PRIVATE TEST'));
        $other = $this->candidate();
        foreach (['preview','content','download'] as $action) {
            $this->get(route('hr.recruitment.attachments.'.$action,['candidates',$other,$file->id]))->assertNotFound();
        }
        config(['role_permissions.always_enforce_permissions'=>['page.hr']]);
        $this->actingAs(User::factory()->create());
        foreach (['preview','content','download'] as $action) $this->get(route('hr.recruitment.attachments.'.$action,['candidates',$file->record_id,$file->id]))->assertForbidden();
        auth()->logout();
        $this->get(route('hr.recruitment.attachments.content',['candidates',$file->record_id,$file->id]))->assertRedirect(route('login'));
    }

    public function test_pdf_image_and_text_preview_return_correct_type_and_escape_markup(): void
    {
        foreach ([UploadedFile::fake()->createWithContent('test.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"), UploadedFile::fake()->image('test.png')] as $upload) {
            $file = $this->upload($upload);
            $this->get(route('hr.recruitment.attachments.content',['candidates',$file->record_id,$file->id]))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        }
        $file = $this->upload(UploadedFile::fake()->createWithContent('test.txt','<script>TEST</script>'));
        $this->get(route('hr.recruitment.attachments.content',['candidates',$file->record_id,$file->id]))->assertOk()->assertSee('&lt;script&gt;TEST&lt;/script&gt;',false)->assertDontSee('<script>',false);
    }

    public function test_word_and_excel_are_rendered_locally_and_excel_sheet_selection_is_bounded(): void
    {
        $path = tempnam(sys_get_temp_dir(),'rec-doc');
        $zip = new \ZipArchive(); $zip->open($path,\ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml','<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>WORD TEST tuyển dụng</w:t></w:r></w:p></w:body></w:document>'); $zip->close();
        try { $file = $this->upload(new UploadedFile($path,'test.docx',null,null,true)); }
        finally { @unlink($path); }
        $this->get(route('hr.recruitment.attachments.content',['candidates',$file->record_id,$file->id]))->assertOk()->assertSee('WORD TEST tuyển dụng')->assertHeader('Content-Security-Policy');
        $path = tempnam(sys_get_temp_dir(),'rec-xls');
        $book = new Spreadsheet(); $book->getActiveSheet()->setCellValue('A1','EXCEL TEST')->setCellValue('A201','HIDDEN ROW'); $book->createSheet()->setTitle('Sheet hai')->setCellValue('A1','SECOND TEST');
        (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        try { $file = $this->upload(new UploadedFile($path,'test.xlsx',null,null,true)); }
        finally { @unlink($path); }
        $url = route('hr.recruitment.attachments.content',['candidates',$file->record_id,$file->id]);
        $this->get($url)->assertOk()->assertSee('EXCEL TEST')->assertDontSee('HIDDEN ROW');
        $this->get($url.'?sheet=1')->assertOk()->assertSee('SECOND TEST');
        $this->get($url.'?sheet=99999')->assertOk()->assertSee('SECOND TEST');
    }
}
