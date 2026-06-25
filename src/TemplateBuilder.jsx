import React, { useState } from 'react';
import { Plus, Trash2, ChevronUp, ChevronDown, Settings2, Save, Type } from 'lucide-react';

const FIELD_TYPES = [
  { value: 'text', label: 'Short Text' },
  { value: 'file', label: 'File Upload (Pro)' },
  { value: 'select', label: 'Combo Box / Dropdown' },
  { value: 'radio', label: 'Radio Buttons' }
];

export default function App() {
  const [fields, setFields] = useState(() => {
    if (typeof window !== 'undefined' && window.cieloTemplateData && window.cieloTemplateData.fields) {
      try { return JSON.parse(window.cieloTemplateData.fields); } catch (e) {}
    }
    return [];
  });
  const [expandedFieldId, setExpandedFieldId] = useState(null);
  const [toast, setToast] = useState(null);

  const generateId = () => `field_${Math.random().toString(36).substr(2, 9)}`;
  const slugify = (text) => text.toString().toLowerCase().replace(/\s+/g, '_').replace(/[^\w\-]+/g, '');

  const addField = () => {
    const newField = {
      id: generateId(),
      key: `new_field_${fields.length + 1}`,
      label: 'New Field',
      type: 'text',
      priceModifier: 0,
      feeType: 'per_item', // per_item or per_order
      weightModifier: 0,
      skuModifier: '',
      isRequired: false,
      isPro: false,
      options: '',
      conditionTarget: '',
      conditionValue: '',
      allowedRoles: '' // comma separated roles e.g. customer,wholesale
    };
    setFields([...fields, newField]);
    setExpandedFieldId(newField.id);
  };

  const updateField = (id, key, value) => {
    setFields(fields.map(f => {
      if (f.id === id) {
        const updated = { ...f, [key]: value };
        if (key === 'label' && f.key === slugify(f.label)) updated.key = slugify(value);
        return updated;
      }
      return f;
    }));
  };

  const saveToWordPress = () => {
    const jsonData = JSON.stringify(fields, null, 2);
    const hiddenInput = document.querySelector('textarea[name="cielo_template_data"]');
    if (hiddenInput) hiddenInput.value = jsonData;
    setToast('Configuration applied! Click the WordPress "Update" button to save.');
    setTimeout(() => setToast(null), 4000);
  };

  return (
    <div className="bg-gray-50 text-gray-800 p-4 font-sans border border-gray-200 mt-4 rounded-xl">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold">Field Configurations</h2>
        <button onClick={addField} className="px-4 py-2 bg-blue-600 text-white rounded-lg"><Plus className="inline w-4 h-4 mr-2"/> Add Field</button>
      </div>

      <div className="space-y-4">
        {fields.map((field, index) => (
          <div key={field.id} className="bg-white rounded-xl shadow-sm border border-gray-200">
            {/* Header */}
            <div className="flex items-center p-4 cursor-pointer hover:bg-gray-50" onClick={() => setExpandedFieldId(expandedFieldId === field.id ? null : field.id)}>
              <div className="flex-1 font-semibold">{field.label || 'Untitled Field'} <span className="text-gray-400 text-sm ml-2">({field.key})</span></div>
              <button onClick={(e) => { e.stopPropagation(); setFields(fields.filter(f => f.id !== field.id)); }} className="text-red-500 p-2"><Trash2 className="w-4 h-4"/></button>
            </div>
            
            {/* Settings */}
            {expandedFieldId === field.id && (
              <div className="p-5 border-t border-gray-100 bg-gray-50 grid grid-cols-2 gap-4">
                <div><label className="block text-sm font-medium mb-1">Label</label><input type="text" value={field.label} onChange={e => updateField(field.id, 'label', e.target.value)} className="w-full border p-2 rounded"/></div>
                <div><label className="block text-sm font-medium mb-1">Database Key</label><input type="text" value={field.key} onChange={e => updateField(field.id, 'key', e.target.value)} className="w-full border p-2 rounded"/></div>
                <div><label className="block text-sm font-medium mb-1">Type</label>
                  <select value={field.type} onChange={e => updateField(field.id, 'type', e.target.value)} className="w-full border p-2 rounded">
                    {FIELD_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </div>
                <div><label className="block text-sm font-medium mb-1">Fee Amount (+ / -)</label><input type="number" step="0.01" value={field.priceModifier} onChange={e => updateField(field.id, 'priceModifier', e.target.value)} className="w-full border p-2 rounded"/></div>
                <div><label className="block text-sm font-medium mb-1">Fee Type</label>
                  <select value={field.feeType} onChange={e => updateField(field.id, 'feeType', e.target.value)} className="w-full border p-2 rounded">
                    <option value="per_item">Multiply by Quantity (Per Item)</option>
                    <option value="per_order">Flat Fee (Per Order)</option>
                  </select>
                </div>
                <div><label className="block text-sm font-medium mb-1">Weight Modifier (+ lbs/kg)</label><input type="number" step="0.1" value={field.weightModifier} onChange={e => updateField(field.id, 'weightModifier', e.target.value)} className="w-full border p-2 rounded"/></div>
                
                {(field.type === 'select' || field.type === 'radio') && (
                  <div className="col-span-2"><label className="block text-sm font-medium mb-1">Choices (Comma Separated)</label><textarea value={field.options} onChange={e => updateField(field.id, 'options', e.target.value)} className="w-full border p-2 rounded"/></div>
                )}
                
                <div className="col-span-2 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                  <h4 className="font-semibold text-sm mb-2 text-blue-800">Conditional Logic (Show only if...)</h4>
                  <div className="flex gap-2">
                    <input type="text" placeholder="Target Field Key (e.g. add_engraving)" value={field.conditionTarget} onChange={e => updateField(field.id, 'conditionTarget', e.target.value)} className="border p-2 rounded flex-1 text-sm"/>
                    <select className="border p-2 rounded"><option>==</option></select>
                    <input type="text" placeholder="Value (e.g. Yes)" value={field.conditionValue} onChange={e => updateField(field.id, 'conditionValue', e.target.value)} className="border p-2 rounded flex-1 text-sm"/>
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      <button onClick={saveToWordPress} className="mt-6 w-full bg-green-600 text-white font-bold py-3 rounded-lg"><Save className="inline w-5 h-5 mr-2"/> Apply Field Configurations</button>
      
      {toast && <div className="fixed bottom-6 right-6 bg-gray-900 text-white px-6 py-3 rounded-lg z-50">{toast}</div>}
    </div>
  );
}