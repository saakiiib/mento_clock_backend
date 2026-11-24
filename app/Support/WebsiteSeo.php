<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class WebsiteSeo {
 public const KINDS=['features'=>'Features','industries'=>'Industries','blog'=>'Resources'];
 public static function base(array $s): string { $url=rtrim($s['site_url']??'','/'); return preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~i',$url)?$url:'https://mentoclock.com'; }
 public static function path(object $p): string {return '/'.$p->kind.'/'.$p->slug;}
 public static function pages(){return DB::table('website_seo_pages')->where('published',true)->where('indexable',true)->orderBy('id');}
 public static function settings(): array {return DB::table('marketing_site_settings')->whereIn('key',['seo_verification','seo_social_title','seo_social_description'])->pluck('value','key')->all();}
 public static function json(array $schema): string {return json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
 public static function schema(array $s,string $path,string $title,?object $page=null): array {
  $base=self::base($s); $graph=[['@type'=>'Organization','@id'=>$base.'/#organization','name'=>'Mento Software','url'=>'https://www.mentosoftware.co.uk','logo'=>$base.'/marketing/assets/logo.png'],['@type'=>'WebSite','@id'=>$base.'/#website','name'=>'MentoClock','url'=>$base.'/','publisher'=>['@id'=>$base.'/#organization']]];
  if($path==='/') $graph[]=['@type'=>'SoftwareApplication','name'=>'MentoClock','url'=>$base.'/','applicationCategory'=>'BusinessApplication','operatingSystem'=>'iOS, Android, Web','publisher'=>['@id'=>$base.'/#organization']];
  else { $crumbs=[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$base.'/']]; if($page)$crumbs[]=['@type'=>'ListItem','position'=>2,'name'=>self::KINDS[$page->kind],'item'=>$base.'/'.$page->kind]; $crumbs[]=['@type'=>'ListItem','position'=>count($crumbs)+1,'name'=>$page->heading??$title,'item'=>$base.$path]; $graph[]=['@type'=>'BreadcrumbList','itemListElement'=>$crumbs]; if($page && $page->kind==='blog')$graph[]=['@type'=>'Article','headline'=>$page->heading,'description'=>$page->description,'mainEntityOfPage'=>$base.$path,'author'=>['@id'=>$base.'/#organization'],'publisher'=>['@id'=>$base.'/#organization'],'dateModified'=>\Illuminate\Support\Carbon::parse($page->updated_at)->toAtomString(),'image'=>$base.'/marketing/assets/social-card.jpg']; }
  return ['@context'=>'https://schema.org','@graph'=>$graph];
 }
}
