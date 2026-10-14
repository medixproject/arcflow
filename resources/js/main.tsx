import React from 'react';
import { createRoot } from 'react-dom/client';
import { createPublicClient, http } from 'viem';
const App=()=>{const [status,setStatus]=React.useState('Checking Arc RPC…');React.useEffect(()=>{const rpc=(import.meta.env.VITE_ARC_RPC_URL as string|undefined);if(!rpc){setStatus('Arc RPC is configured server-side.');return;}const client=createPublicClient({transport:http(rpc)});client.getBlockNumber().then(n=>setStatus(`Arc reachable · block ${n}`)).catch(()=>setStatus('Arc RPC unavailable'));},[]);return <main style={{fontFamily:'system-ui',padding:40,maxWidth:900,margin:'auto'}}><p>ARCFLOW</p><h1>USDC payment infrastructure for Arc.</h1><p>{status}</p></main>};
createRoot(document.getElementById('app')!).render(<App/>);
