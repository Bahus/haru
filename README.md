Haru
====

Born in the spring


# PhingX


�������������� tasks ��� ����������� ������ �� ���������� porperties �������, ���������� �������� miao.

### XmlMergeTask

��������� "�������" ��������� xml ������ � ����. ������������ ��� ������������ ������� 
� ����������� �� ���� ��������� (dev, test, prod) � �������������� ���������������� ��������.

������ �������� ������ ������ �������:

* common.xml - �������� ��� ��������.
* extends/develop.xml - �������� ��������������� ��������, �������� *dev* ���������.
* extends/users/user.xml - �������� ���������������� ���������

��������� ������ ���� �� ����� �������� ������ ���������������� ����� ������� �������.


    //file: common.xml
    <config>
      <display_errors>0</display_errors>
	    <email>prod@project.com</email>
    </config>

    //file: extends/develop.xml
    <config>
        <display_errors>1</display_errors>	
    </config>

    //file: build.xml
    ...
    <xmlmerge srcFileList="common.xml,extends/develop.xml" dstFile="result.xml" />
    ...
    
    //file: result.xml
    <config>
	    <display_errors>1</display_errors>
	    <email>prod@project.com</email>
    </config>
    
### XmlPropertyResolveTask

������������ ��� ��������� ����� ������� d �������� ini, php, xml �� ��������� ����� ������� � ��������.

������
	//file: build/property.xml
	<config>
		<path>
			<root>/www/project</root>
			<tmp>${config.path.root}/tmp</tmp
		</path>
	<config>
	
	...
	<xmlmerge srcFileList="build/property.xml" dstFile="config/property.xml" type="xml" />
	...
	
	//file: config/property.xml
	<config>
		<path>
			<root>/www/project</root>
			<tmp>/www/project/tmp</tmp
		</path>
	<config>
	
������ *xml* �����������, ���������� ��� perl, python � ��. ������, 
*php* - ��� ���� ��� ��������� ����� ��� ������� xml � php-��������.