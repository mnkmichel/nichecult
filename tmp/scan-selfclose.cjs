const fs=require('fs')
const src=fs.readFileSync('app/pages/duftkuration.vue','utf8')
const tpl=src.split('<template>')[1].split('</template>')[0]
const re=/<([a-zA-Z][\w-]*)\b[\s\S]*?\/>/g
let m
while((m=re.exec(tpl))){
  const tag=m[1]
  const before=tpl.slice(0,m.index)
  const line=before.split(/\r?\n/).length
  console.log(line, tag)
}
