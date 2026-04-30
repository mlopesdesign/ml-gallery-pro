import zipfile
import os

zip_name = 'ml-gallery-pro-v0.22.37.zip'
root_dir = 'ml-gallery-pro'

def create_zip(zip_name, source_dir):
    with zipfile.ZipFile(zip_name, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # We want to package the source_dir ITSELF as the first entry
        # and then all its contents.
        for root, dirs, files in os.walk(source_dir):
            for file in files:
                file_path = os.path.join(root, file)
                # Ensure we use forward slashes for internal paths
                archive_name = file_path.replace(os.sep, '/')
                zipf.write(file_path, archive_name)
            for d in dirs:
                dir_path = os.path.join(root, d)
                # Folders must end with / for some parsers
                archive_name = dir_path.replace(os.sep, '/') + '/'
                zipf.write(dir_path, archive_name)

if __name__ == '__main__':
    # Move to the project dir
    os.chdir(r'd:\ANTIGRAVITY\ml-gallery-pro')
    if os.path.exists(zip_name):
        os.remove(zip_name)
    create_zip(zip_name, root_dir)
    print(f"Zip {zip_name} created successfully with forward slashes.")
