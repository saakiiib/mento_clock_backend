<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::create('website_seo_pages', function(Blueprint $t){$t->id();$t->string('kind',20);$t->string('slug',120);$t->string('heading',200);$t->string('seo_title',200);$t->string('description',500);$t->text('intro');$t->text('body');$t->text('faq')->nullable();$t->string('social_title',200)->nullable();$t->string('social_description',500)->nullable();$t->string('social_image',255)->nullable();$t->string('canonical',255)->nullable();$t->boolean('published')->default(false);$t->boolean('indexable')->default(true);$t->timestamps();$t->unique(['kind','slug']);});
  Schema::create('website_seo_redirects',function(Blueprint $t){$t->id();$t->string('source',180)->unique();$t->string('destination',180);$t->timestamps();});
  foreach (require database_path('data/website_seo_pages.php') as $row) DB::table('website_seo_pages')->insert($row+['published'=>true,'indexable'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $defaults=['seo_title'=>['MentoClock | Employee attendance, clock-in and reports','Staff Attendance & Clock-In Software UK | MentoClock'],'hero_title'=>['Clock in, plan rotas and manage holidays — all in one app.','Staff attendance & workforce management — made simple.'],'hero_description'=>['The simple workforce app for UK businesses. Track time, build rotas and manage leave, from anywhere.','Staff attendance software for UK businesses. Track clock-ins, manage rotas and holidays, and prepare timesheet reports from anywhere.']];
  foreach($defaults as $key=>[$old,$new]) DB::table('marketing_site_settings')->where('key',$key)->where('value',$old)->update(['value'=>$new,'updated_at'=>now()]);
  DB::table('marketing_site_settings')->where('key','seo_description')->whereIn('value',['','Simplify employee clock-in and clock-out across your shops and workplaces. Explore MentoClock attendance reports, client management and flexible packages.'])->update(['value'=>'Manage staff attendance, GPS clock-ins, rotas and holidays with MentoClock. Request a private trial for your UK business.']);
 }
 public function down(): void {Schema::dropIfExists('website_seo_redirects');Schema::dropIfExists('website_seo_pages');}
};
