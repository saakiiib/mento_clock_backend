<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class WebsiteSeoTest extends TestCase {
 use RefreshDatabase;
 public function test_homepage_has_search_and_sharing_metadata():void {
  $this->get('/')->assertOk()->assertSee('Staff Attendance &amp; Clock-In Software UK',false)->assertSee('/favicon.ico')->assertSee('application/ld+json')->assertSee('social-card.jpg')->assertSee('https://mentoclock.com/');
 }
 public function test_public_pages_have_unique_metadata_and_sitemap_entries():void {
  $this->get('/features')->assertOk()->assertSee('/features/gps-clock-in-app');
  $this->get('/features/gps-clock-in-app')->assertOk()->assertSee('GPS clock-in app for employees')->assertSee('https://mentoclock.com/features/gps-clock-in-app')->assertSee('BreadcrumbList');
  $this->get('/blog/digital-timesheets-vs-paper')->assertOk()->assertSee('"@type":"Article"',false);
  $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8')->assertSee('https://mentoclock.com/features/gps-clock-in-app')->assertDontSee('/platform');
  $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://mentoclock.com/sitemap.xml');
 }
 public function test_draft_noindex_and_redirect_behaviour():void {
  DB::table('website_seo_pages')->where('slug','gps-clock-in-app')->update(['published'=>false]);
  $this->get('/features/gps-clock-in-app')->assertNotFound();
  $this->get('/sitemap.xml')->assertDontSee('gps-clock-in-app');
  DB::table('website_seo_pages')->where('slug','gps-clock-in-app')->update(['published'=>true,'indexable'=>false]);
  $this->get('/features/gps-clock-in-app')->assertOk()->assertSee('noindex,nofollow');
  $this->get('/sitemap.xml')->assertDontSee('gps-clock-in-app');
  DB::table('website_seo_redirects')->insert(['source'=>'/features/old-attendance','destination'=>'/features/staff-attendance-software','created_at'=>now(),'updated_at'=>now()]);
  $this->get('/features/old-attendance')->assertStatus(301)->assertRedirect('/features/staff-attendance-software');
 }
 public function test_anonymous_user_cannot_access_seo_admin():void {
  $this->get('/platform/website/seo')->assertRedirect('/login');
  $this->post('/platform/website/seo/pages',['heading'=>'Unauthorised'])->assertRedirect('/login');
 }
}
