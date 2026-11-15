/* Presentation-only icon renderer. Leaves attributes, data and business logic unchanged. */
function auraPolishIcons(html){
 const icons={'♛':'crown','🏛️':'bank','🪙':'coins','🎯':'target','🚀':'rocket','⭐':'star','🛒':'cart','▦':'calendar','🔒':'lock','📍':'pin','🦋':'wings','♾️':'infinity','💜':'gem','✦':'sparkle','☑':'checklist','▣':'folder','▥':'wallet','◈':'wallet','◎':'target','★':'star','⌘':'link','◯':'user','♧':'bell','♙':'lock'};
 const pattern=/(🏛️|🪙|🎯|🚀|⭐|🛒|🔒|📍|🦋|♾️|💜|♛|▦|✦|☑|▣|▥|◈|◎|★|⌘|◯|♧|♙)/gu;
 return html.split(/(<[^>]*>)/g).map((chunk,i)=>i%2?chunk:chunk.replace(pattern,c=>`<svg class="aura-svg aura-svg-${icons[c]}" aria-hidden="true"><use href="${window.AURA_ASSET_BASE||'assets'}/icons.svg#${icons[c]}"></use></svg>`)).join('');
}
