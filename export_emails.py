#!/usr/bin/env python3
import json
import urllib.request
import urllib.parse
import os
import argparse
import string
from datetime import datetime

# Configurações da API do Grafana do Top Massagens
GRAFANA_URL = "https://grafana.topmassagens.com.br/api/ds/query?ds_type=mysql"
DATASOURCE_UID = "dfpqllglhd88wf"
AUTH_USER = "timetop"
AUTH_PASS = "77308515"

def run_query(sql_query):
    # Codificar credenciais em Base64 para Basic Auth
    import base64
    auth_str = f"{AUTH_USER}:{AUTH_PASS}"
    auth_b64 = base64.b64encode(auth_str.encode('utf-8')).decode('utf-8')
    
    payload = {
        "queries": [
            {
                "datasource": {"type": "mysql", "uid": DATASOURCE_UID},
                "rawSql": sql_query,
                "format": "table",
                "refId": "A"
            }
        ],
        "from": "now-30d",
        "to": "now"
    }
    
    req = urllib.request.Request(
        GRAFANA_URL,
        data=json.dumps(payload).encode('utf-8'),
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Basic {auth_b64}",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        },
        method="POST"
    )
    
    try:
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read().decode('utf-8'))
            return parse_grafana_response(data)
    except Exception as e:
        print(f"Erro ao consultar a API do Grafana: {e}")
        return None

def parse_grafana_response(response_data):
    try:
        results = response_data.get('results', {})
        if not results or 'A' not in results:
            print("Nenhum resultado retornado da query.")
            return []
        
        frames = results['A'].get('frames', [])
        if not frames:
            return []
            
        frame = frames[0]
        fields = [f['name'] for f in frame['schema']['fields']]
        values = frame['data']['values']
        
        if not values or not values[0]:
            return []
            
        rows = list(zip(*values))
        
        # Converter lista de tuplas para dicionários
        result_dicts = []
        for row in rows:
            row_dict = {}
            for field, val in zip(fields, row):
                row_dict[field] = val
            result_dicts.append(row_dict)
        return result_dicts
    except Exception as e:
        print(f"Erro ao processar resposta do Grafana: {e}")
        return []

def sanitize_filename(name):
    # Permitir letras, números, espaços e alguns acentos do português brasileiro
    valid_chars = "-_.() " + string.ascii_letters + string.digits
    sanitized = ''.join(c for c in name if c in valid_chars or c in 'áéíóúâêîôûãõçÁÉÍÓÚÂÊÎÔÛÃÕÇ')
    return sanitized.strip()[:100]

def main():
    parser = argparse.ArgumentParser(description="Script para exportar emails enviados do banco do Top Massagens via API do Grafana.")
    parser.add_argument("--inicio", help="Data de início (formato: YYYY-MM-DD HH:MM:SS ou YYYY-MM-DD)", required=True)
    parser.add_argument("--fim", help="Data de fim (formato: YYYY-MM-DD HH:MM:SS ou YYYY-MM-DD)", default=None)
    parser.add_argument("--modo", choices=["templates", "todos", "lista"], default="templates", 
                        help="templates (exporta um modelo HTML único por assunto), todos (exporta todos os emails individuais), lista (apenas gera um resumo CSV)")
    parser.add_argument("--saida", default="emails_exportados", help="Diretório ou arquivo de saída")
    
    args = parser.parse_args()
    
    start_date = args.inicio
    end_date = args.fim if args.fim else datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    print(f"Buscando emails enviados no período de: {start_date} até {end_date}...")
    
    if args.modo == "templates":
        # Agrupar por título para extrair os templates únicos enviados
        sql = f"""
        SELECT titulo, MIN(data) as primeira_data, COUNT(*) as total_disparos, MIN(texto) as texto
        FROM log_email_enviado 
        WHERE data >= '{start_date}' AND data <= '{end_date}'
        GROUP BY titulo
        ORDER BY total_disparos DESC
        """
        rows = run_query(sql)
        if not rows:
            print("Nenhum email encontrado no período selecionado.")
            return
            
        os.makedirs(args.saida, exist_ok=True)
        print(f"Exportando {len(rows)} templates de e-mail únicos para a pasta '{args.saida}'...")
        
        for idx, row in enumerate(rows):
            titulo = row.get('titulo') or "Sem Titulo"
            total = row.get('total_disparos', 0)
            texto_html = row.get('texto') or ""
            
            raw_data = row.get('primeira_data')
            data_str = ""
            if raw_data:
                try:
                    data_str = datetime.fromtimestamp(raw_data / 1000).strftime("%Y-%m-%d")
                except:
                    data_str = "data_invalida"
                    
            safe_title = sanitize_filename(titulo)
            filename = f"{data_str}_{safe_title}.html"
            filepath = os.path.join(args.saida, filename)
            
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(f"<!--\nAssunto: {titulo}\nTotal de disparos no período: {total}\n-->\n")
                f.write(texto_html)
                
            print(f"[{idx+1}/{len(rows)}] Exportado: {filename} ({total} envios)")
            
    elif args.modo == "todos":
        # Exportar cada um dos emails individuais enviados
        sql = f"""
        SELECT id, email, titulo, data, texto
        FROM log_email_enviado 
        WHERE data >= '{start_date}' AND data <= '{end_date}'
        ORDER BY id DESC
        """
        rows = run_query(sql)
        if not rows:
            print("Nenhum email encontrado no período selecionado.")
            return
            
        os.makedirs(args.saida, exist_ok=True)
        print(f"Exportando {len(rows)} emails individuais para a pasta '{args.saida}'...")
        
        for idx, row in enumerate(rows):
            email_id = row.get('id')
            destinatario = row.get('email')
            titulo = row.get('titulo') or "Sem Titulo"
            texto_html = row.get('texto') or ""
            raw_data = row.get('data')
            
            data_str = ""
            if raw_data:
                try:
                    data_str = datetime.fromtimestamp(raw_data / 1000).strftime("%Y-%m-%d_%H-%M-%S")
                except:
                    data_str = "data_invalida"
                    
            safe_title = sanitize_filename(titulo)
            filename = f"{data_str}_id{email_id}_{destinatario}_{safe_title}.html"
            filepath = os.path.join(args.saida, filename)
            
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(f"<!--\nID: {email_id}\nPara: {destinatario}\nAssunto: {titulo}\nData: {data_str}\n-->\n")
                f.write(texto_html)
                
            if (idx + 1) % 100 == 0 or idx + 1 == len(rows):
                print(f"Exportados {idx+1}/{len(rows)} arquivos...")
                
    elif args.modo == "lista":
        # Gerar apenas planilha CSV contendo a relação dos envios
        sql = f"""
        SELECT id, email, titulo, data, retorno
        FROM log_email_enviado 
        WHERE data >= '{start_date}' AND data <= '{end_date}'
        ORDER BY id DESC
        """
        rows = run_query(sql)
        if not rows:
            print("Nenhum email encontrado no período selecionado.")
            return
            
        import csv
        csv_file = args.saida if args.saida.endswith('.csv') else f"{args.saida}.csv"
        print(f"Exportando listagem de {len(rows)} emails para o arquivo '{csv_file}'...")
        
        with open(csv_file, mode='w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow(["ID", "Destinatario", "Assunto", "Data", "Status Retorno"])
            for row in rows:
                raw_data = row.get('data')
                data_str = ""
                if raw_data:
                    try:
                        data_str = datetime.fromtimestamp(raw_data / 1000).strftime("%Y-%m-%d %H:%M:%S")
                    except:
                        data_str = "N/A"
                writer.writerow([
                    row.get('id'),
                    row.get('email'),
                    row.get('titulo'),
                    data_str,
                    row.get('retorno')
                ])
        print("Exportação concluída com sucesso!")

if __name__ == "__main__":
    main()
