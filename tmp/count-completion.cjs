const fs=require('fs')
const s=fs.readFileSync('app/pages/duftkuration.vue','utf8')
const seg=s.split('<!-- Completion screen -->')[1].split('</template>')[0]
console.log('open', (seg.match(/<div\b/g)||[]).length)
console.log('close', (seg.match(/<\/div>/g)||[]).length)
