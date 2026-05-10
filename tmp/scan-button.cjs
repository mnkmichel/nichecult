const fs=require('fs')
const src=fs.readFileSync('app/pages/duftkuration.vue','utf8')
const tpl=src.split('<template>')[1].split('</template>')[0]
const re=/<button\b[\s\S]*?\/>/g
let m, found=0
while((m=re.exec(tpl))){found++;const line=tpl.slice(0,m.index).split(/\r?\n/).length;console.log('button self-close at',line)}
console.log('found',found)
