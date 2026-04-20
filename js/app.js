// Smart Semester — Shared Utilities
function getUser(){const u=localStorage.getItem('ss_user');if(!u){window.location.href='../index.html';return null;}return JSON.parse(u);}
function logout(){localStorage.removeItem('ss_user');window.location.href='../index.html';}
function renderSidebarUser(user){
  const el=document.getElementById('sidebar-user');if(!el)return;
  el.innerHTML=`<div class="user-avatar">${user.photo?`<img src="${user.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover"/>`:user.name.charAt(0)}</div><div class="user-info"><div class="user-name">${user.name}</div><div class="user-role">${user.role==='student'?(user.dept+' · '+(user.sem||'')):'Teacher · '+(user.dept||'')}</div></div>`;
}
function showPage(id){
  document.querySelectorAll('.page-section').forEach(p=>p.classList.add('d-none'));
  const el=document.getElementById('page-'+id);
  if(el){el.classList.remove('d-none');el.classList.add('fade-in');}
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.toggle('active',n.dataset.page===id));
}
function toast(msg,type='success'){
  const t=document.createElement('div');
  const colors={success:'rgba(0,212,170,.2);border:1px solid rgba(0,212,170,.4);color:#00D4AA',error:'rgba(255,107,107,.2);border:1px solid rgba(255,107,107,.4);color:#FF6B6B',info:'rgba(74,144,226,.2);border:1px solid rgba(74,144,226,.4);color:#4A90E2'};
  t.style.cssText=`position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:700;animation:fadeIn .3s ease;max-width:320px;background:${colors[type]};font-family:'Nunito',sans-serif;`;
  t.textContent=msg;document.body.appendChild(t);setTimeout(()=>t.remove(),3000);
}
function openModal(id){document.getElementById(id).style.display='flex';}
function closeModal(id){document.getElementById(id).style.display='none';}
function chartDefaults(){
  return{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#9BA3C4',font:{size:11}}},y:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#9BA3C4',font:{size:11}}}}};
}
const DEMO={
  subjects:['Data Structures','DBMS','OS','Computer Networks','Soft. Engg.'],
  scores:[72,85,61,78,90],
  tests:[
    {id:1,title:'Unit Test 1 — Data Structures',date:'2025-03-15',duration:30,marks:20,status:'completed',score:16},
    {id:2,title:'Unit Test 2 — DBMS',date:'2025-03-28',duration:30,marks:20,status:'completed',score:18},
    {id:3,title:'Mid Semester — All Subjects',date:'2025-04-10',duration:60,marks:50,status:'upcoming',score:null},
    {id:4,title:'Mock Exam — Final Prep (60 Marks)',date:'2025-05-01',duration:90,marks:60,status:'upcoming',score:null},
  ],
  materials:[
    {id:1,title:'DS Unit 1 — Arrays & Linked Lists',subject:'Data Structures',type:'pdf',size:'2.4 MB',date:'2025-03-01'},
    {id:2,title:'DBMS ER Diagram Tutorial',subject:'DBMS',type:'video',size:'45 min',date:'2025-03-05'},
    {id:3,title:'OS Process Scheduling Notes',subject:'OS',type:'pdf',size:'1.8 MB',date:'2025-03-10'},
    {id:4,title:'CN Previous Year Questions 2023',subject:'Computer Networks',type:'pyq',size:'3.1 MB',date:'2025-03-12'},
    {id:5,title:'SE SDLC Video Lecture',subject:'Soft. Engg.',type:'video',size:'60 min',date:'2025-03-14'},
    {id:6,title:'DS PYQ 2022–2024 Collection',subject:'Data Structures',type:'pyq',size:'4.2 MB',date:'2025-03-18'},
  ],
  messages:[
    {id:1,from:'Prof. Anita Verma',role:'teacher',msgs:[{txt:'Please submit your OS assignment by Friday.',time:'10:30 AM',sent:false},{txt:'Yes maam, will submit before deadline.',time:'10:45 AM',sent:true}],photo:'https://api.dicebear.com/7.x/avataaars/svg?seed=anita'},
    {id:2,from:'Prof. Rajan Kumar',role:'teacher',msgs:[{txt:'Unit test 2 results are out. Check your dashboard.',time:'Yesterday',sent:false}],photo:'https://api.dicebear.com/7.x/avataaars/svg?seed=rajan'},
    {id:3,from:'Priya Mehta',role:'student',msgs:[{txt:'Can you share the notes for DS Chapter 4?',time:'Mon',sent:false},{txt:'I have uploaded them. Check study materials.',time:'Mon',sent:true}],photo:'https://api.dicebear.com/7.x/avataaars/svg?seed=priya'},
  ],
  students:[
    {name:'Rahul Sharma',roll:'CSE501',dept:'CSE',sem:'Sem 5',avg:82,attendance:88,scores:[78,85,76,82,90]},
    {name:'Priya Mehta',roll:'CSE502',dept:'CSE',sem:'Sem 5',avg:74,attendance:92,scores:[70,78,68,74,82]},
    {name:'Arjun Das',roll:'CSE503',dept:'CSE',sem:'Sem 5',avg:91,attendance:95,scores:[90,92,88,91,96]},
    {name:'Sneha Patel',roll:'CSE504',dept:'CSE',sem:'Sem 5',avg:58,attendance:72,scores:[55,60,52,58,65]},
    {name:'Kiran Rao',roll:'CSE505',dept:'CSE',sem:'Sem 5',avg:67,attendance:80,scores:[64,70,62,67,72]},
    {name:'Amit Singh',roll:'CSE506',dept:'CSE',sem:'Sem 5',avg:79,attendance:85,scores:[76,82,74,79,84]},
  ],
  leaderboard:[
    {rank:1,name:'Arjun Das',avg:91,badge:'🥇'},{rank:2,name:'Rahul Sharma',avg:82,badge:'🥈'},
    {rank:3,name:'Amit Singh',avg:79,badge:'🥉'},{rank:4,name:'Priya Mehta',avg:74,badge:''},
    {rank:5,name:'Kiran Rao',avg:67,badge:''},{rank:6,name:'Sneha Patel',avg:58,badge:''},
  ]
};
