import subprocess, re, sys
src, dst, keep = sys.argv[1], sys.argv[2], float(sys.argv[3])
dur = float(subprocess.run(['ffprobe','-v','error','-show_entries','format=duration','-of','csv=p=0',src],capture_output=True,text=True).stdout)
err = subprocess.run(['ffmpeg','-hide_banner','-i',src,'-af','silencedetect=n=-38dB:d=0.12','-f','null','-'],capture_output=True,text=True).stderr
starts=[float(x) for x in re.findall(r'silence_start: ([\d.]+)',err)]
ends=[float(x) for x in re.findall(r'silence_end: ([\d.]+)',err)]
sil=list(zip(starts,ends+[dur]*(len(starts)-len(ends))))
segs=[];cur=0.0
for s,e in sil:
    if s<=0.05: cur=max(cur,e-0.03); continue
    if e>=dur-0.05: segs.append((cur,s+0.06)); cur=None; break
    gap=e-s; pad=min(gap,keep)/2
    segs.append((cur,s+pad)); cur=e-pad
if cur is not None: segs.append((cur,dur))
parts=[];
for i,(a,b) in enumerate(segs): parts.append(f"[0:a]atrim={a:.3f}:{b:.3f},asetpts=PTS-STARTPTS[a{i}]")
f=';'.join(parts)+';'+''.join(f'[a{i}]' for i in range(len(segs)))+f'concat=n={len(segs)}:v=0:a=1[o]'
subprocess.run(['ffmpeg','-hide_banner','-loglevel','error','-y','-i',src,'-filter_complex',f,'-map','[o]','-ar','48000','-ac','2',dst],check=True)
print(dst, round(sum(b-a for a,b in segs),2))
