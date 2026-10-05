<template>
  <div class="flex lg:flex-col overflow-x-auto lg:overflow-visible gap-1.5 pb-2 lg:pb-0">
    <h2 class="hidden lg:block text-[14px] font-bold text-slate-800 mb-3 px-4">Settings Menu</h2>
    
    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold bg-[#5138ed]/10 text-[#5138ed] flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
      General Settings
    </button>
    
    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
      Exam Settings
    </button>
    
    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
      Security Settings
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
      Notification Settings
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
      Email Settings
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
      System Preferences
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
      Backup & Restore
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
      Integrations
    </button>

    <button class="flex-shrink-0 lg:w-full text-left px-4 py-2.5 rounded-xl text-[13px] font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center gap-3 transition-colors min-h-[44px] whitespace-nowrap">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
      Audit Log
    </button>
  </div>
</template>
