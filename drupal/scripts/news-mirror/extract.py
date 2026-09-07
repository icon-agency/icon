# Mirroring news stories from iconagency.com.au
#
# 1. List the story URLs (the old site paginates, ?page=N):
#      curl -sL "https://iconagency.com.au/news?page=0" | grep -o 'href="/news/[^"]*"' …
#    Put the ones to mirror in urls.txt, one path per line, then download them:
#      i=0; while read u; do i=$((i+1)); curl -sL -A Mozilla/5.0 "https://iconagency.com.au$u" -o pages/$(printf %02d $i).html; done < urls.txt
# 2. Extract: python3 extract.py pages articles.json lists   (lists/ = the /news?page=N pages, for each story's LISTING image)
#    → title, date, category, tile (og:image), banner (the header image, when the
#      story has one), and the body as blocks: prose (cleaned HTML for basic_html),
#      figure (the ORIGINAL file behind the old site's image style), remote_video
#      (YouTube — the ID is not in the page; put the embed URL in import.php's $embeds),
#      local_video (an mp4 the old site hosts; put it and a cover in $films).
# 3. Download every image in articles.json to a folder; shrink anything huge
#    (sips -Z 2400; big PNG photos → JPEG); push the folder, articles.json and
#    import.php into the container at /tmp/import/news.
# 4. ddev drush php:script /tmp/import/news/import.php
#    Idempotent: a story is matched by TITLE (so a sample story with the same
#    title is updated, not duplicated), then by alias; the alias becomes the
#    old site's path. Sample stories with no counterpart are unpublished.
import re,sys,json,html,os
BASE="https://drupal.iconagency.com.au/files/agency/"
def orig(url):
    # styles/<style>/public/<path>?itok → files/agency/<path>
    m=re.search(r"/public/([^?]+)",url)
    return BASE+m.group(1) if m else url.split("?")[0]
def clean_html(h):
    h=re.sub(r"\s*data-v-[a-z0-9]+(=\"[^\"]*\")?","",h)
    h=re.sub(r"</?span[^>]*>","",h)
    h=re.sub(r'\s(dir|style|class|id|rel|target|aria-level|role)="[^"]*"',"",h)
    h=re.sub(r"<p>(\s|&nbsp;)*</p>","",h)
    h=re.sub(r"<(h[1-6])>\s*</\1>","",h)
    h=re.sub(r"\s+"," ",h)
    h=re.sub(r"\s*</(p|h[1-6]|li|ul|ol|blockquote)>\s*",r"</\1>\n",h)
    return h.strip()
def blocks_of(seg):
    out=[]
    for m in re.finditer(r'<div class="paragraph-type-(wysiwyg-text|media)[^"]*"[^>]*>',seg):
        kind=m.group(1); start=m.end()
        nxt=re.search(r'<div class="paragraph-type-|<div class="share-links',seg[start:])
        chunk=seg[start:start+nxt.start()] if nxt else seg[start:]
        if kind=="wysiwyg-text":
            c=re.search(r'<div class="content[^"]*"[^>]*>(.*)',chunk,re.S)
            body=c.group(1) if c else chunk
            # strip trailing closing divs
            body=re.sub(r"(</div>\s*)+$","",body)
            h=clean_html(body)
            if re.sub(r"<[^>]+>","",h).strip(): out.append({"type":"prose","html":h})
        else:
            if "media-remote-video" in chunk:
                img=re.search(r'<img[^>]+alt="([^"]*)"',chunk)
                out.append({"type":"remote_video","title":html.unescape(img.group(1)) if img else "", "thumb":re.search(r'src="([^"]+)"',chunk).group(1)})
            elif "media-embed--media-video" in chunk:
                out.append({"type":"local_video","raw":re.sub(r"\s+"," ",chunk)[:400]})
            else:
                img=re.search(r'<img[^>]+>',chunk)
                if img:
                    alt=re.search(r'alt="([^"]*)"',img.group(0)); src=re.search(r'src="([^"]+)"',img.group(0))
                    out.append({"type":"figure","url":orig(src.group(1)),"alt":html.unescape(alt.group(1)) if alt else ""})
    return out
# The listing's tile (a third argument: a folder of the old site's /news?page=N
# pages) is the story's tile; og:image is only the fallback — the two differ
# where a story has its own listing image (the old site's "News listing image").
listing={}
if len(sys.argv)>3:
    lists="".join(open(os.path.join(sys.argv[3],f)).read() for f in sorted(os.listdir(sys.argv[3])) if f.endswith(".html"))
    for m in re.finditer(r'<a[^>]+href="(/news/[^"]+)"[^>]*>(.*?)</a>',lists,re.S):
        href,inner=m.groups()
        img=re.search(r'<img[^>]+>',inner)
        if img and href not in listing:
            src=re.search(r'src="([^"]+)"',img.group(0)); alt=re.search(r'alt="([^"]*)"',img.group(0))
            if src: listing[href]={"url":orig(src.group(1)),"alt":html.unescape(alt.group(1)) if alt else ""}
res=[]
for f in sorted(os.listdir(sys.argv[1])):
    if not f.endswith(".html"): continue
    s=open(os.path.join(sys.argv[1],f)).read()
    t=re.search(r"<h1[^>]*>(.*?)</h1>",s,re.S); title=re.sub(r"\s+"," ",html.unescape(re.sub(r"<[^>]+>","",t.group(1)))).strip()
    d=re.search(r'datetime="([^"]+)"',s).group(1)
    c=re.search(r'<li[^>]*class="category"[^>]*>(.*?)</li>',s,re.S); cat=re.sub(r"<[^>]+>","",c.group(1)).strip()
    og=re.search(r'property="og:image"[^>]*content="([^"]*)"',s) or re.search(r'content="([^"]*)"[^>]*property="og:image"',s)
    mb=re.search(r'<div class="media-background[^"]*"[^>]*>(.*?)<section',s,re.S); inner=mb.group(1) if mb else ""
    hi=re.search(r'<img[^>]+>',inner); banner=None
    if hi:
        banner={"url":orig(re.search(r'src="([^"]+)"',hi.group(0)).group(1)),"alt":html.unescape((re.search(r'alt="([^"]*)"',hi.group(0)) or [None,""])[1] if re.search(r'alt="([^"]*)"',hi.group(0)) else "")}
    canon=re.search(r'<link[^>]+rel="canonical"[^>]+href="([^"]+)"',s) or re.search(r'property="og:url"[^>]*content="([^"]*)"',s)
    body_start=s.find("paragraph-type-wysiwyg-text"); body_start=s.rfind("<div",0,body_start)
    body_end=s.find('class="share-links'); seg=s[body_start:body_end]
    path=re.sub(r"^https?://[^/]+","",canon.group(1)) if canon else ""
    tile=listing.get(path) or {"url":orig(og.group(1)),"alt":title}
    if not tile.get("alt"): tile["alt"]=title
    res.append({"file":f,"title":title,"date":d[:10],"category":cat.lower().replace(" ","-"),"tile":tile,"banner":banner,"blocks":blocks_of(seg),"source":canon.group(1) if canon else ""})
json.dump(res,open(sys.argv[2],"w"),indent=1,ensure_ascii=False)
for r in res:
    print(r["file"],"|",r["category"],"|",r["date"],"|",r["title"][:50],"| tile:",r["tile"]["url"].split("/")[-1][:40],"| banner:",r["banner"]["url"].split("/")[-1][:30] if r["banner"] else "-")
    for b in r["blocks"]:
        if b["type"]=="prose": print("    prose",len(b["html"]),"chars:",re.sub(r"<[^>]+>","",b["html"])[:70].replace("\n"," "))
        elif b["type"]=="figure": print("    figure",b["url"].split("/")[-1][:50],"|",b["alt"][:50])
        else: print("   ",b["type"],b.get("title",""),b.get("raw","")[:200])
